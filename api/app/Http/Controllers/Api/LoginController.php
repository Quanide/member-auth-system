<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
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

    public function __construct(private readonly AuthService $auth) {}

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
            $user = $this->auth->login(
                $request->string('email')->value(),
                $request->string('password')->value(),
                $request->boolean('remember'),
            );
        } catch (\Throwable $e) {
            // 只有失败才累加计数，成功登入不该消耗额度
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw $e;
        }

        RateLimiter::clear($key);

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
}
