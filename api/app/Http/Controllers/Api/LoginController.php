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
     * 同一「信箱 + IP」組合每分鐘最多 10 次嘗試。
     * 刻意高於 AuthService::MAX_FAILED_ATTEMPTS（5 次即鎖定帳號）：
     * 讓語義更精確的「帳號鎖定」先觸發，使用者看到的是「已鎖定 15 分鐘」
     * 而不是籠統的「請求過於頻繁」。這一層只用來兜住換帳號掃號的流量。
     */
    private const MAX_ATTEMPTS = 10;

    private const DECAY_SECONDS = 60;

    /** 通過密碼後，多久內必須完成雙因素驗證 */
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
                "嘗試過於頻繁，請於 {$seconds} 秒後再試",
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
            // 只有失敗才累加計數，成功登入不該消耗額度
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw $e;
        }

        RateLimiter::clear($key);

        // 啟用雙因素的帳號，密碼正確只算通過第一關，
        // 這裡先不建立登入狀態，只在 session 裡記一筆待驗證。
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING_ID, $user->id);
            $request->session()->put(self::PENDING_AT, now()->timestamp);
            $request->session()->put(self::PENDING_REMEMBER, $request->boolean('remember'));

            return ApiResponse::ok([
                'two_factor_required' => true,
                'message' => '請輸入驗證 App 上的 6 位數驗證碼',
            ]);
        }

        $this->auth->completeLogin($user, $request->boolean('remember'));

        return ApiResponse::ok([
            'two_factor_required' => false,
            'user' => new UserResource($user),
            'message' => '登入成功',
        ]);
    }

    /** 雙因素第二關：接受 TOTP 驗證碼或恢復碼 */
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
                '驗證逾時，請重新登入',
                401,
            );
        }

        $user = User::find($pendingId);

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            $this->forgetPending($request);

            return ApiResponse::error(ErrorCode::UNAUTHENTICATED, '請重新登入', 401);
        }

        $challengeKey = '2fa:'.$user->id.'|'.$request->ip();

        // 驗證碼只有 6 位數，必須限流，否則可以暴力枚舉
        if (RateLimiter::tooManyAttempts($challengeKey, 5)) {
            $seconds = RateLimiter::availableIn($challengeKey);

            return ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS,
                "驗證失敗次數過多，請於 {$seconds} 秒後再試",
                429,
                extra: ['retry_after' => $seconds],
            );
        }

        if (! $this->twoFactor->challenge($user, $validated['code'])) {
            RateLimiter::hit($challengeKey, 300);
            $this->audit->log(AuditAction::TwoFactorChallengeFailed, $user);

            return ApiResponse::error(
                ErrorCode::INVALID_TOKEN,
                '驗證碼不正確',
                422,
                ['code' => ['驗證碼不正確']],
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
