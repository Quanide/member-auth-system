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
    /** 连续失败几次触发锁定 */
    public const MAX_FAILED_ATTEMPTS = 5;

    /** 锁定时长（分钟） */
    public const LOCK_MINUTES = 15;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Request $request,
    ) {}

    /**
     * 注册。
     * 创建后立即寄验证信，但不阻断登入——未验证用户可以进站，
     * 只是敏感操作（改邮箱等）会被 EnsureEmailIsVerified 拦下。
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = new User;
            $user->name = $data['name'];
            $user->email = mb_strtolower(trim($data['email']));
            $user->password = $data['password']; // 由 casts 的 hashed 自动加密
            $user->role = UserRole::Member;
            $user->status = UserStatus::Active;
            // 注册即建立会话，等同一次登入，一并记下来源
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
     * 验证帐号密码，但**不建立会话**。
     *
     * 与 completeLogin() 分开是为了塞进双因素这一步：
     * 密码对了只代表通过第一关，启用 2FA 的帐号还要再验一次验证码。
     *
     * 防爆破分两层：
     *  1) 路由上的 throttle middleware 按 IP + 邮箱限流，挡住高频扫号
     *  2) 这里按帐号累计失败次数并锁定，换 IP 也绕不过
     */
    public function attempt(string $email, string $password): User
    {
        $email = mb_strtolower(trim($email));
        $user = User::where('email', $email)->first();

        // 帐号不存在时也走一次 Hash::check，让响应耗时与「密码错误」一致，
        // 避免通过时间差探测邮箱是否已注册。
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
     * 完成登入：建立会话并记录轨迹。
     * 未启用 2FA 时紧接在 attempt() 之后呼叫；启用时则在验证码通过后才呼叫。
     */
    public function completeLogin(User $user, bool $remember = false): void
    {
        $this->markLoginSuccess($user, $remember);
    }

    /** 失败计数 +1，超阈值则锁定 */
    private function recordFailedAttempt(User $user): void
    {
        $user->failed_login_count++;

        if ($user->failed_login_count >= self::MAX_FAILED_ATTEMPTS) {
            $user->locked_until = now()->addMinutes(self::LOCK_MINUTES);
            $user->failed_login_count = 0; // 锁定期结束后重新计数
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

        // 固定会话攻击防护：登入后换一个 session id
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
