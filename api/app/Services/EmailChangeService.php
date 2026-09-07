<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Exceptions\DomainException;
use App\Models\EmailChangeRequest;
use App\Models\User;
use App\Notifications\VerifyNewEmail;
use App\Support\ErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 變更信箱走「雙確認」：
 * 新地址驗證通過前不動 users.email，
 * 使用者填錯也只是收不到信，不會把自己鎖在門外。
 */
final class EmailChangeService
{
    private const TOKEN_TTL_MINUTES = 60;

    public function __construct(private readonly AuditLogger $audit) {}

    public function request(User $user, string $newEmail, string $currentPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new DomainException(
                ErrorCode::PASSWORD_MISMATCH,
                '目前密碼不正確',
                422,
                ['current_password' => ['目前密碼不正確']],
            );
        }

        $newEmail = mb_strtolower(trim($newEmail));

        if ($newEmail === $user->email) {
            throw new DomainException(
                ErrorCode::VALIDATION_FAILED,
                '新信箱與目前信箱相同',
                422,
                ['new_email' => ['新信箱與目前信箱相同']],
            );
        }

        if (User::where('email', $newEmail)->exists()) {
            throw new DomainException(
                ErrorCode::EMAIL_TAKEN,
                '此信箱已被其他帳號使用',
                422,
                ['new_email' => ['此信箱已被其他帳號使用']],
            );
        }

        // 明文 token 只出現在郵件裡，庫中存 SHA-256 摘要
        $token = Str::random(64);

        DB::transaction(function () use ($user, $newEmail, $token): void {
            // 作廢該使用者之前未使用的申請，避免多封郵件同時有效
            EmailChangeRequest::where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            EmailChangeRequest::create([
                'user_id' => $user->id,
                'new_email' => $newEmail,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            ]);
        });

        $user->notify(new VerifyNewEmail($newEmail, $token));

        $this->audit->log(AuditAction::EmailChangeRequested, $user, ['new_email' => $newEmail]);
    }

    /** 點擊新信箱裡的連結後落庫 */
    public function confirm(string $token): User
    {
        $request = EmailChangeRequest::where('token_hash', hash('sha256', $token))->first();

        if ($request === null || ! $request->isUsable()) {
            throw new DomainException(
                ErrorCode::INVALID_TOKEN,
                '驗證連結無效或已過期，請重新申請',
                422,
            );
        }

        $user = $request->user;

        // 申請期間該信箱可能已被別人註冊，落庫前再查一次
        if (User::where('email', $request->new_email)->whereKeyNot($user->id)->exists()) {
            throw new DomainException(
                ErrorCode::EMAIL_TAKEN,
                '此信箱已被其他帳號使用',
                422,
            );
        }

        DB::transaction(function () use ($user, $request): void {
            $user->forceFill([
                'email' => $request->new_email,
                'email_verified_at' => now(),
            ])->save();

            $request->forceFill(['used_at' => now()])->save();
        });

        $this->audit->log(AuditAction::EmailChanged, $user, ['new_email' => $request->new_email]);

        return $user;
    }
}
