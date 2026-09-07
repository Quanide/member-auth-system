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

    /** 重寄驗證信 */
    public function send(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::error(ErrorCode::EMAIL_ALREADY_VERIFIED, '信箱已完成驗證', 409);
        }

        $key = 'verify-email:'.$user->id;

        // 每分鐘一封，避免被當成郵件轟炸的跳板
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS,
                "驗證信已寄出，請於 {$seconds} 秒後再重試",
                429,
                extra: ['retry_after' => $seconds],
            );
        }

        RateLimiter::hit($key, 60);

        $user->sendEmailVerificationNotification();
        $this->audit->log(AuditAction::EmailVerificationSent, $user);

        return ApiResponse::message('驗證信已重新寄出，請查收信箱');
    }

    /**
     * 處理郵件裡的驗證連結。
     * 路由帶 signed 中間件，Laravel 會先校驗籤名與有效期，籤名不符直接 403。
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return ApiResponse::error(ErrorCode::INVALID_TOKEN, '驗證連結無效', 422);
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::ok([
                'user' => new UserResource($user),
                'message' => '信箱先前已完成驗證',
            ]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        $this->audit->log(AuditAction::EmailVerified, $user);

        return ApiResponse::ok([
            'user' => new UserResource($user),
            'message' => '信箱驗證成功',
        ]);
    }
}
