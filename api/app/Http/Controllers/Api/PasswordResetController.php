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
                "請求過於頻繁，請於 {$seconds} 秒後再試",
                429,
                extra: ['retry_after' => $seconds],
            );
        }

        RateLimiter::hit($key, 900);

        $this->passwords->sendResetLink($request->string('email')->value());

        // 無論該信箱是否註冊過都回同一句話，避免帳號枚舉
        return ApiResponse::message('若該信箱已註冊，我們已寄出重設密碼的連結');
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwords->reset($request->only('email', 'token', 'password'));

        return ApiResponse::message('密碼已重設，請使用新密碼登入');
    }
}
