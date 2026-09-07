<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Support\ErrorCode;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 头像处理。
 *
 * 安全要点：
 *  - 不信任客户端给的扩展名与 Content-Type，用 getimagesize() 读文件头判定真实类型
 *  - 服务端一律重新编码输出，顺带剥掉 EXIF（含 GPS 定位）与可能夹带的脚本
 *  - 存储路径用随机文件名，避免目录穿越与覆盖他人文件
 */
final class AvatarService
{
    /** 输出边长（正方形） */
    private const OUTPUT_SIZE = 512;

    /** 允许的真实图片类型 */
    private const ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** 解码前的像素上限，防「解压炸弹」把内存吃光 */
    private const MAX_PIXELS = 8000 * 8000;

    public function __construct(private readonly AuditLogger $audit) {}

    public function update(User $user, UploadedFile $file): string
    {
        $source = $this->decode($file);

        try {
            $square = $this->cropToSquare($source);
        } finally {
            imagedestroy($source);
        }

        try {
            [$binary, $extension] = $this->encode($square);
        } finally {
            imagedestroy($square);
        }

        $path = 'avatars/'.Str::random(40).'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        $old = $user->avatar_path;
        $user->forceFill(['avatar_path' => $path])->save();

        // 先写新的再删旧的：中途失败也不会让用户头像凭空消失
        if ($old !== null) {
            Storage::disk('public')->delete($old);
        }

        $this->audit->log(AuditAction::AvatarUpdated, $user);

        return $path;
    }

    public function remove(User $user): void
    {
        $old = $user->avatar_path;

        if ($old === null) {
            return;
        }

        $user->forceFill(['avatar_path' => null])->save();
        Storage::disk('public')->delete($old);

        $this->audit->log(AuditAction::AvatarRemoved, $user);
    }

    /** 读文件头校验并解码为 GD 资源 */
    private function decode(UploadedFile $file): GdImage
    {
        $info = @getimagesize($file->getRealPath());

        if ($info === false || ! in_array($info[2], self::ALLOWED_TYPES, true)) {
            throw new DomainException(
                ErrorCode::VALIDATION_FAILED,
                '头像必须是 JPG、PNG 或 WebP 图片',
                422,
                ['avatar' => ['头像必须是 JPG、PNG 或 WebP 图片']],
            );
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new DomainException(
                ErrorCode::VALIDATION_FAILED,
                '图片尺寸过大，请压缩后再上传',
                422,
                ['avatar' => ['图片尺寸过大，请压缩后再上传']],
            );
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($image === false) {
            throw new DomainException(
                ErrorCode::VALIDATION_FAILED,
                '图片已损坏或无法解析',
                422,
                ['avatar' => ['图片已损坏或无法解析']],
            );
        }

        return $image;
    }

    /** 居中裁切成正方形并缩放到固定边长 */
    private function cropToSquare(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $canvas = imagecreatetruecolor(self::OUTPUT_SIZE, self::OUTPUT_SIZE);

        // 保留 PNG/WebP 的透明通道，否则透明区会变成黑块
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, self::OUTPUT_SIZE, self::OUTPUT_SIZE, $transparent);

        imagecopyresampled(
            $canvas,
            $source,
            0, 0,
            (int) (($width - $side) / 2),
            (int) (($height - $side) / 2),
            self::OUTPUT_SIZE, self::OUTPUT_SIZE,
            $side, $side,
        );

        return $canvas;
    }

    /**
     * 编码输出。优先 WebP（同画质体积约为 JPEG 的 70%），
     * GD 未编译 WebP 支持时回落到 PNG。
     *
     * @return array{0: string, 1: string}
     */
    private function encode(GdImage $image): array
    {
        ob_start();

        if (function_exists('imagewebp')) {
            imagewebp($image, null, 82);
            $extension = 'webp';
        } else {
            imagepng($image, null, 6);
            $extension = 'png';
        }

        return [(string) ob_get_clean(), $extension];
    }
}
