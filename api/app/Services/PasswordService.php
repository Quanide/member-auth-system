<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Support\ErrorCode;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class PasswordService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * 已登入状态下修改密码。
     * 必须验证旧密码：防止有人捡到没锁屏的电脑就直接改掉密码。
     */
    public function change(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new DomainException(
                ErrorCode::PASSWORD_MISMATCH,
                '目前密码不正确',
                422,
                ['current_password' => ['目前密码不正确']],
            );
        }

        DB::transaction(function () use ($user, $newPassword): void {
            $user->password = $newPassword;
            $user->setRememberToken(Str::random(60));
            $user->save();

            // 改密后踢掉其他装置的会话，只保留当前这台
            $this->revokeOtherSessions($user);
        });

        $this->audit->log(AuditAction::PasswordChanged, $user);
    }

    /**
     * 发送重设密码邮件。
     * 无论邮箱是否存在都返回相同结果，避免被用来枚举已注册邮箱。
     */
    public function sendResetLink(string $email): void
    {
        $email = mb_strtolower(trim($email));

        Password::sendResetLink(['email' => $email]);

        $user = User::where('email', $email)->first();
        $this->audit->log(AuditAction::PasswordResetRequested, $user, ['email' => $email]);
    }

    /**
     * 用邮件里的 token 重设密码。
     *
     * @param  array{email: string, token: string, password: string}  $data
     */
    public function reset(array $data): void
    {
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                // 能收到重设信即证明邮箱可用，顺手把锁定和失败计数清掉
                'failed_login_count' => 0,
                'locked_until' => null,
            ])->save();

            $this->revokeAllSessions($user);

            event(new PasswordReset($user));

            $this->audit->log(AuditAction::PasswordResetCompleted, $user);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw new DomainException(
                ErrorCode::INVALID_TOKEN,
                '重设连结无效或已过期，请重新申请',
                422,
                ['token' => ['重设连结无效或已过期，请重新申请']],
            );
        }
    }

    /** 作废该用户除当前会话外的所有 session（session driver = database） */
    private function revokeOtherSessions(User $user): void
    {
        $currentId = session()->getId();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentId)
            ->delete();

        // Sanctum API token 一并作废
        $user->tokens()->delete();
    }

    private function revokeAllSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();

        if (Auth::id() === $user->id) {
            Auth::guard('web')->logout();
        }
    }
}
