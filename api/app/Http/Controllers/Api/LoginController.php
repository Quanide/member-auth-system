<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\TwoFactorService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class LoginController extends Controller
{
    /**
     * 同一「邮箱 + IP」组合每分钟最多 10 次尝试。
     * 刻意高于 AuthService::MAX_FAILED_ATTEMPTS（5 次即锁定帐号）：
     * 让语义更精确的「帐号锁定」先触发，用户看到的是「已锁定 15 分钟」
     * 而不是笼统的「请求过于频繁」。这一层只用来兜住换帐号扫号的流量。
     */
    private const MAX_ATTEMPTS = 10;

    private const DECAY_SECONDS = 60;

    /** 通过密码后，多久内必须完成双因素验证 */
    private const TWO_FACTOR_WINDOW_SECONDS = 300;

    private const PENDING_ID = '2fa.pending_id';

    private const PENDING_AT = '2fa.pending_at';

    private const PENDING_REMEMBER = '2fa.remember';

    public function __construct(
        private readonly AuthService $auth,
        private readonly TwoFactorService $twoFactor,
        private readonly AuditLogger $audit,
    ) {}

    public function store(LoginRequest $request): JsonResponse
    {
        $key = $request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            return ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS,
                "尝试过于频繁，请于 {$seconds} 秒后再试",
                429,
                extra: ['retry_after' => $seconds],
            );
        }

        try {
            $user = $this->auth->attempt(
                $request->string('email')->value(),
                $request->string('password')->value(),
            );
        } catch (\Throwable $e) {
            // 只有失败才累加计数，成功登入不该消耗额度
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw $e;
        }

        RateLimiter::clear($key);

        // 启用双因素的帐号，密码正确只算通过第一关，
        // 这里先不建立登入状态，只在 session 里记一笔待验证。
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING_ID, $user->id);
            $request->session()->put(self::PENDING_AT, now()->timestamp);
            $request->session()->put(self::PENDING_REMEMBER, $request->boolean('remember'));

            return ApiResponse::ok([
                'two_factor_required' => true,
                'message' => '请输入验证 App 上的 6 位数验证码',
            ]);
        }

        $this->auth->completeLogin($user, $request->boolean('remember'));

        return ApiResponse::ok([
            'two_factor_required' => false,
            'user' => new UserResource($user),
            'message' => '登入成功',
        ]);
    }

    /** 双因素第二关：接受 TOTP 验证码或恢复码 */
    public function twoFactorChallenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $pendingId = $request->session()->get(self::PENDING_ID);
        $pendingAt = (int) $request->session()->get(self::PENDING_AT, 0);

        if ($pendingId === null || now()->timestamp - $pendingAt > self::TWO_FACTOR_WINDOW_SECONDS) {
            $this->forgetPending($request);

            return ApiResponse::error(
                ErrorCode::UNAUTHENTICATED,
                '验证逾时，请重新登入',
                401,
            );
        }

        $user = User::find($pendingId);

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            $this->forgetPending($request);

            return ApiResponse::error(ErrorCode::UNAUTHENTICATED, '请重新登入', 401);
        }

        $challengeKey = '2fa:'.$user->id.'|'.$request->ip();

        // 验证码只有 6 位数，必须限流，否则可以暴力枚举
        if (RateLimiter::tooManyAttempts($challengeKey, 5)) {
            $seconds = RateLimiter::availableIn($challengeKey);

            return ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS,
                "验证失败次数过多，请于 {$seconds} 秒后再试",
                429,
                extra: ['retry_after' => $seconds],
            );
        }

        if (! $this->twoFactor->challenge($user, $validated['code'])) {
            RateLimiter::hit($challengeKey, 300);
            $this->audit->log(AuditAction::TwoFactorChallengeFailed, $user);

            return ApiResponse::error(
                ErrorCode::INVALID_TOKEN,
                '验证码不正确',
                422,
                ['code' => ['验证码不正确']],
            );
        }

        RateLimiter::clear($challengeKey);

        $remember = (bool) $request->session()->get(self::PENDING_REMEMBER, false);
        $this->forgetPending($request);

        $this->auth->completeLogin($user, $remember);

        return ApiResponse::ok([
            'user' => new UserResource($user),
            'message' => '登入成功',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->auth->logout();

        return ApiResponse::message('已登出');
    }

    private function forgetPending(Request $request): void
    {
        $request->session()->forget([self::PENDING_ID, self::PENDING_AT, self::PENDING_REMEMBER]);
    }
}
