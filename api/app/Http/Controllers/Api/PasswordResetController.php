<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

final class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordService $passwords) {}

    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        $key = 'forgot-password:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS,
                "请求过于频繁，请于 {$seconds} 秒后再试",
                429,
                extra: ['retry_after' => $seconds],
            );
        }

        RateLimiter::hit($key, 900);

        $this->passwords->sendResetLink($request->string('email')->value());

        // 无论该邮箱是否注册过都回同一句话，避免帐号枚举
        return ApiResponse::message('若该邮箱已注册，我们已寄出重设密码的连结');
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwords->reset($request->only('email', 'token', 'password'));

        return ApiResponse::message('密码已重设，请使用新密码登入');
    }
}
