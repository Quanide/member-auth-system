<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Support\ErrorCode;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * 双因素认证（TOTP，RFC 6238）。
 *
 * 流程刻意分成「产生 → 确认 → 启用」三步：
 * 使用者必须先用 App 产出一组正确的验证码，才算真的绑定成功，
 * 否则扫码失败的人会把自己锁在门外。
 */
final class TwoFactorService
{
    /** 恢复码数量与长度 */
    private const RECOVERY_CODE_COUNT = 8;

    private readonly Google2FA $engine;

    public function __construct(private readonly AuditLogger $audit)
    {
        $this->engine = new Google2FA;
    }

    /**
     * 产生密钥与 QR Code，此时尚未启用。
     *
     * @return array{secret: string, qr_code: string, otpauth_url: string}
     */
    public function generate(User $user): array
    {
        $secret = $this->engine->generateSecretKey(32);

        // 先存起来但不设 confirmed_at，使用者输入验证码后才真正生效
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $issuer = (string) config('app.name');
        $otpauth = $this->engine->getQRCodeUrl($issuer, $user->email, $secret);

        return [
            'secret' => $secret,
            'qr_code' => $this->renderQrCode($otpauth),
            'otpauth_url' => $otpauth,
        ];
    }

    /**
     * 确认绑定：验证码正确才启用，并回传一次性恢复码。
     *
     * @return array<int, string>
     */
    public function confirm(User $user, string $code): array
    {
        if ($user->two_factor_secret === null) {
            throw new DomainException(ErrorCode::VALIDATION_FAILED, '请先产生绑定用的 QR Code', 422);
        }

        if ($user->two_factor_confirmed_at !== null) {
            throw new DomainException(ErrorCode::VALIDATION_FAILED, '双因素认证已启用', 409);
        }

        if (! $this->verifyCode($user->two_factor_secret, $code)) {
            throw new DomainException(
                ErrorCode::INVALID_TOKEN,
                '验证码不正确，请确认手机时间是否准确',
                422,
                ['code' => ['验证码不正确']],
            );
        }

        $plainCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            // 恢复码同样只存雜凑，遗失只能重新产生，不能反查
            'two_factor_recovery_codes' => array_map(
                static fn (string $code): string => Hash::make($code),
                $plainCodes,
            ),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->audit->log(AuditAction::TwoFactorEnabled, $user);

        return $plainCodes;
    }

    /** 关闭 2FA，需验证密码 */
    public function disable(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw new DomainException(
                ErrorCode::PASSWORD_MISMATCH,
                '密码不正确',
                422,
                ['password' => ['密码不正确']],
            );
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->log(AuditAction::TwoFactorDisabled, $user);
    }

    /** 重新产生恢复码，旧的立即失效 */
    public function regenerateRecoveryCodes(User $user, string $password): array
    {
        if (! Hash::check($password, $user->password)) {
            throw new DomainException(
                ErrorCode::PASSWORD_MISMATCH,
                '密码不正确',
                422,
                ['password' => ['密码不正确']],
            );
        }

        $plainCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(
                static fn (string $code): string => Hash::make($code),
                $plainCodes,
            ),
        ])->save();

        $this->audit->log(AuditAction::TwoFactorRecoveryRegenerated, $user);

        return $plainCodes;
    }

    /**
     * 登入时的第二道验证：先试 TOTP，再试恢复码。
     * 恢复码用掉即作废。
     */
    public function challenge(User $user, string $code): bool
    {
        if ($user->two_factor_secret !== null && $this->verifyCode($user->two_factor_secret, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    private function verifyCode(string $secret, string $code): bool
    {
        // window = 1：容许前后各 30 秒，吸收手机与伺服器的时间误差
        return $this->engine->verifyKey($secret, preg_replace('/\s+/', '', $code) ?? '', 1);
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes;

        if (! is_array($codes) || $codes === []) {
            return false;
        }

        $normalized = strtoupper(trim($code));

        foreach ($codes as $index => $hashed) {
            if (! Hash::check($normalized, $hashed)) {
                continue;
            }

            unset($codes[$index]);

            $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

            $this->audit->log(AuditAction::TwoFactorRecoveryUsed, $user, [
                'remaining' => count($codes),
            ]);

            return true;
        }

        return false;
    }

    /** @return array<int, string> */
    private function generateRecoveryCodes(): array
    {
        return array_map(
            // 形如 A1B2C3D4-E5F6G7H8，好念也好抄
            static fn (): string => strtoupper(Str::random(8).'-'.Str::random(8)),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }

    /** 直接输出 SVG data URI，前端不必再引入 QR 产生器 */
    private function renderQrCode(string $otpauthUrl): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(256, 1),
                new SvgImageBackEnd,
            ),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($otpauthUrl));
    }
}
