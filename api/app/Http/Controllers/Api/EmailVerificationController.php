<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class EmailVerificationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** 重寄验证信 */
    public function send(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::error(ErrorCode::EMAIL_ALREADY_VERIFIED, '邮箱已完成验证', 409);
        }

        $key = 'verify-email:'.$user->id;

        // 每分钟一封，避免被当成邮件轰炸的跳板
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS,
                "验证信已寄出，请于 {$seconds} 秒后再重试",
                429,
                extra: ['retry_after' => $seconds],
            );
        }

        RateLimiter::hit($key, 60);

        $user->sendEmailVerificationNotification();
        $this->audit->log(AuditAction::EmailVerificationSent, $user);

        return ApiResponse::message('验证信已重新寄出，请查收邮箱');
    }

    /**
     * 处理邮件里的验证连结。
     * 路由带 signed 中间件，Laravel 会先校验签名与有效期，签名不符直接 403。
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return ApiResponse::error(ErrorCode::INVALID_TOKEN, '验证连结无效', 422);
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::ok([
                'user' => new UserResource($user),
                'message' => '邮箱先前已完成验证',
            ]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        $this->audit->log(AuditAction::EmailVerified, $user);

        return ApiResponse::ok([
            'user' => new UserResource($user),
            'message' => '邮箱验证成功',
        ]);
    }
}
