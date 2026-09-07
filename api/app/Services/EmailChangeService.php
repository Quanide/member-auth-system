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
 * 变更邮箱走「双确认」：
 * 新地址验证通过前不动 users.email，
 * 用户填错也只是收不到信，不会把自己锁在门外。
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
                '目前密码不正确',
                422,
                ['current_password' => ['目前密码不正确']],
            );
        }

        $newEmail = mb_strtolower(trim($newEmail));

        if ($newEmail === $user->email) {
            throw new DomainException(
                ErrorCode::VALIDATION_FAILED,
                '新邮箱与目前邮箱相同',
                422,
                ['new_email' => ['新邮箱与目前邮箱相同']],
            );
        }

        if (User::where('email', $newEmail)->exists()) {
            throw new DomainException(
                ErrorCode::EMAIL_TAKEN,
                '此邮箱已被其他帐号使用',
                422,
                ['new_email' => ['此邮箱已被其他帐号使用']],
            );
        }

        // 明文 token 只出现在邮件里，库中存 SHA-256 摘要
        $token = Str::random(64);

        DB::transaction(function () use ($user, $newEmail, $token): void {
            // 作废该用户之前未使用的申请，避免多封邮件同时有效
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

    /** 点击新邮箱里的连结后落库 */
    public function confirm(string $token): User
    {
        $request = EmailChangeRequest::where('token_hash', hash('sha256', $token))->first();

        if ($request === null || ! $request->isUsable()) {
            throw new DomainException(
                ErrorCode::INVALID_TOKEN,
                '验证连结无效或已过期，请重新申请',
                422,
            );
        }

        $user = $request->user;

        // 申请期间该邮箱可能已被别人注册，落库前再查一次
        if (User::where('email', $request->new_email)->whereKeyNot($user->id)->exists()) {
            throw new DomainException(
                ErrorCode::EMAIL_TAKEN,
                '此邮箱已被其他帐号使用',
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
