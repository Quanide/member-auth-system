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
 * 雙因素認證（TOTP，RFC 6238）。
 *
 * 流程刻意分成「產生 → 確認 → 啟用」三步：
 * 使用者必須先用 App 產出一組正確的驗證碼，才算真的綁定成功，
 * 否則掃碼失敗的人會把自己鎖在門外。
 */
final class TwoFactorService
{
    /** 恢復碼數量與長度 */
    private const RECOVERY_CODE_COUNT = 8;

    private readonly Google2FA $engine;

    public function __construct(private readonly AuditLogger $audit)
    {
        $this->engine = new Google2FA;
    }

    /**
     * 產生密鑰與 QR Code，此時尚未啟用。
     *
     * @return array{secret: string, qr_code: string, otpauth_url: string}
     */
    public function generate(User $user): array
    {
        $secret = $this->engine->generateSecretKey(32);

        // 先存起來但不設 confirmed_at，使用者輸入驗證碼後才真正生效
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
     * 確認綁定：驗證碼正確才啟用，並回傳一次性恢復碼。
     *
     * @return array<int, string>
     */
    public function confirm(User $user, string $code): array
    {
        if ($user->two_factor_secret === null) {
            throw new DomainException(ErrorCode::VALIDATION_FAILED, '請先產生綁定用的 QR Code', 422);
        }

        if ($user->two_factor_confirmed_at !== null) {
            throw new DomainException(ErrorCode::VALIDATION_FAILED, '雙因素認證已啟用', 409);
        }

        if (! $this->verifyCode($user->two_factor_secret, $code)) {
            throw new DomainException(
                ErrorCode::INVALID_TOKEN,
                '驗證碼不正確，請確認手機時間是否準確',
                422,
                ['code' => ['驗證碼不正確']],
            );
        }

        $plainCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            // 恢復碼同樣只存雜湊，遺失只能重新產生，不能反查
            'two_factor_recovery_codes' => array_map(
                static fn (string $code): string => Hash::make($code),
                $plainCodes,
            ),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->audit->log(AuditAction::TwoFactorEnabled, $user);

        return $plainCodes;
    }

    /** 關閉 2FA，需驗證密碼 */
    public function disable(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw new DomainException(
                ErrorCode::PASSWORD_MISMATCH,
                '密碼不正確',
                422,
                ['password' => ['密碼不正確']],
            );
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->log(AuditAction::TwoFactorDisabled, $user);
    }

    /** 重新產生恢復碼，舊的立即失效 */
    public function regenerateRecoveryCodes(User $user, string $password): array
    {
        if (! Hash::check($password, $user->password)) {
            throw new DomainException(
                ErrorCode::PASSWORD_MISMATCH,
                '密碼不正確',
                422,
                ['password' => ['密碼不正確']],
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
     * 登入時的第二道驗證：先試 TOTP，再試恢復碼。
     * 恢復碼用掉即作廢。
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
        // window = 1：容許前後各 30 秒，吸收手機與伺服器的時間誤差
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

    /** 直接輸出 SVG data URI，前端不必再引入 QR 產生器 */
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
