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
 * 頭像處理。
 *
 * 安全要點：
 *  - 不信任用戶端給的副檔名與 Content-Type，用 getimagesize() 讀檔頭判定真實型別
 *  - 服務端一律重新編碼輸出，順帶剝掉 EXIF（含 GPS 定位）與可能夾帶的腳本
 *  - 儲存路徑用隨機檔名，避免目錄穿越與覆蓋他人檔案
 */
final class AvatarService
{
    /** 輸出邊長（正方形） */
    private const OUTPUT_SIZE = 512;

    /** 允許的真實圖片型別 */
    private const ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** 解碼前的像素上限，防「解壓炸彈」把記憶體吃光 */
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

        // 先寫新的再刪舊的：中途失敗也不會讓使用者頭像憑空消失
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

    /** 讀檔頭校驗並解碼為 GD 資源 */
    private function decode(UploadedFile $file): GdImage
    {
        $info = @getimagesize($file->getRealPath());

        if ($info === false || ! in_array($info[2], self::ALLOWED_TYPES, true)) {
            throw new DomainException(
                ErrorCode::VALIDATION_FAILED,
                '頭像必須是 JPG、PNG 或 WebP 圖片',
                422,
                ['avatar' => ['頭像必須是 JPG、PNG 或 WebP 圖片']],
            );
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new DomainException(
                ErrorCode::VALIDATION_FAILED,
                '圖片尺寸過大，請壓縮後再上傳',
                422,
                ['avatar' => ['圖片尺寸過大，請壓縮後再上傳']],
            );
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($image === false) {
            throw new DomainException(
                ErrorCode::VALIDATION_FAILED,
                '圖片已損壞或無法解析',
                422,
                ['avatar' => ['圖片已損壞或無法解析']],
            );
        }

        return $image;
    }

    /** 居中裁切成正方形並縮放到固定邊長 */
    private function cropToSquare(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $canvas = imagecreatetruecolor(self::OUTPUT_SIZE, self::OUTPUT_SIZE);

        // 保留 PNG/WebP 的透明通道，否則透明區會變成黑塊
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
     * 編碼輸出。優先 WebP（同畫質體積約為 JPEG 的 70%），
     * GD 未編譯 WebP 支援時回落到 PNG。
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
