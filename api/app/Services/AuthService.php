<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\AuthenticationFailedException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class AuthService
{
    /** 連續失敗幾次觸發鎖定 */
    public const MAX_FAILED_ATTEMPTS = 5;

    /** 鎖定時長（分鐘） */
    public const LOCK_MINUTES = 15;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Request $request,
    ) {}

    /**
     * 註冊。
     * 建立後立即寄驗證信，但不阻斷登入——未驗證使用者可以進站，
     * 只是敏感操作（改信箱等）會被 EnsureEmailIsVerified 攔下。
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = new User;
            $user->name = $data['name'];
            $user->email = mb_strtolower(trim($data['email']));
            $user->password = $data['password']; // 由 casts 的 hashed 自動加密
            $user->role = UserRole::Member;
            $user->status = UserStatus::Active;
            // 註冊即建立會話，等同一次登入，一併記下來源
            $user->last_login_at = now();
            $user->last_login_ip = $this->audit->clientIp();
            $user->save();

            return $user;
        });

        $user->sendEmailVerificationNotification();

        $this->audit->log(AuditAction::Register, $user, ['email' => $user->email]);
        $this->audit->log(AuditAction::EmailVerificationSent, $user);

        return $user;
    }

    /**
     * 驗證帳號密碼，但**不建立會話**。
     *
     * 與 completeLogin() 分開是為了塞進雙因素這一步：
     * 密碼對了只代表通過第一關，啟用 2FA 的帳號還要再驗一次驗證碼。
     *
     * 防爆破分兩層：
     *  1) 路由上的 throttle middleware 按 IP + 信箱限流，擋住高頻掃號
     *  2) 這裡按帳號累計失敗次數並鎖定，換 IP 也繞不過
     */
    public function attempt(string $email, string $password): User
    {
        $email = mb_strtolower(trim($email));
        $user = User::where('email', $email)->first();

        // 帳號不存在時也走一次 Hash::check，讓響應耗時與「密碼錯誤」一致，
        // 避免通過時間差探測信箱是否已註冊。
        if ($user === null) {
            Hash::check($password, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            $this->audit->log(AuditAction::LoginFailed, null, ['email' => $email, 'reason' => 'user_not_found']);

            throw AuthenticationFailedException::invalidCredentials(self::MAX_FAILED_ATTEMPTS);
        }

        if ($user->status === UserStatus::Disabled) {
            $this->audit->log(AuditAction::LoginFailed, $user, ['reason' => 'disabled']);

            throw AuthenticationFailedException::disabled();
        }

        if ($user->isTemporarilyLocked()) {
            $this->audit->log(AuditAction::LoginFailed, $user, ['reason' => 'locked']);

            throw AuthenticationFailedException::locked($user->lockRemainingSeconds());
        }

        if (! Hash::check($password, $user->password)) {
            $this->recordFailedAttempt($user);
            throw AuthenticationFailedException::invalidCredentials(
                max(0, self::MAX_FAILED_ATTEMPTS - $user->failed_login_count),
            );
        }

        return $user;
    }

    /**
     * 完成登入：建立會話並記錄軌跡。
     * 未啟用 2FA 時緊接在 attempt() 之後呼叫；啟用時則在驗證碼通過後才呼叫。
     */
    public function completeLogin(User $user, bool $remember = false): void
    {
        $this->markLoginSuccess($user, $remember);
    }

    /** 失敗計數 +1，超閾值則鎖定 */
    private function recordFailedAttempt(User $user): void
    {
        $user->failed_login_count++;

        if ($user->failed_login_count >= self::MAX_FAILED_ATTEMPTS) {
            $user->locked_until = now()->addMinutes(self::LOCK_MINUTES);
            $user->failed_login_count = 0; // 鎖定期結束後重新計數
            $user->save();

            $this->audit->log(AuditAction::AccountLocked, $user, [
                'locked_until' => $user->locked_until->toIso8601String(),
            ]);

            throw AuthenticationFailedException::locked($user->lockRemainingSeconds());
        }

        $user->save();
        $this->audit->log(AuditAction::LoginFailed, $user, ['reason' => 'bad_password']);
    }

    private function markLoginSuccess(User $user, bool $remember): void
    {
        $user->forceFill([
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $this->audit->clientIp(),
        ])->save();

        Auth::guard('web')->login($user, $remember);

        // 固定會話攻擊防護：登入後換一個 session id
        $this->request->session()->regenerate();

        $this->audit->log(AuditAction::LoginSuccess, $user);
    }

    public function logout(): void
    {
        $user = Auth::user();

        Auth::guard('web')->logout();
        $this->request->session()->invalidate();
        $this->request->session()->regenerateToken();

        if ($user instanceof User) {
            $this->audit->log(AuditAction::Logout, $user);
        }
    }
}
