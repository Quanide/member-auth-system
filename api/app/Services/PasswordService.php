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
     * 已登入狀態下修改密碼。
     * 必須驗證舊密碼：防止有人撿到沒鎖屏的電腦就直接改掉密碼。
     */
    public function change(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new DomainException(
                ErrorCode::PASSWORD_MISMATCH,
                '目前密碼不正確',
                422,
                ['current_password' => ['目前密碼不正確']],
            );
        }

        DB::transaction(function () use ($user, $newPassword): void {
            $user->password = $newPassword;
            $user->setRememberToken(Str::random(60));
            $user->save();

            // 改密後踢掉其他裝置的會話，只保留當前這臺
            $this->revokeOtherSessions($user);
        });

        $this->audit->log(AuditAction::PasswordChanged, $user);
    }

    /**
     * 發送重設密碼郵件。
     * 無論信箱是否存在都返回相同結果，避免被用來枚舉已註冊信箱。
     */
    public function sendResetLink(string $email): void
    {
        $email = mb_strtolower(trim($email));

        Password::sendResetLink(['email' => $email]);

        $user = User::where('email', $email)->first();
        $this->audit->log(AuditAction::PasswordResetRequested, $user, ['email' => $email]);
    }

    /**
     * 用郵件裡的 token 重設密碼。
     *
     * @param  array{email: string, token: string, password: string}  $data
     */
    public function reset(array $data): void
    {
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                // 能收到重設信即證明信箱可用，順手把鎖定和失敗計數清掉
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
                '重設連結無效或已過期，請重新申請',
                422,
                ['token' => ['重設連結無效或已過期，請重新申請']],
            );
        }
    }

    /** 作廢該使用者除當前會話外的所有 session（session driver = database） */
    private function revokeOtherSessions(User $user): void
    {
        $currentId = session()->getId();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentId)
            ->delete();

        // Sanctum API token 一併作廢
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
