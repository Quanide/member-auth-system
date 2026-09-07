<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\EmailChangeController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\OverviewController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API 路由
|--------------------------------------------------------------------------
| 认证走 Sanctum 的 SPA 模式：前后端同域部署，凭证放在 httpOnly Cookie 里，
| 前端 JS 读不到 token，配合 CSRF token 抵御跨站请求伪造。
*/

// ── 访客可用 ──────────────────────────────────────────────
Route::prefix('auth')->group(function (): void {
    Route::post('register', [RegisterController::class, 'store'])
        ->middleware('throttle:6,60')          // 同 IP 每小时最多 6 次注册
        ->name('auth.register');

    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:20,1')          // 粗粒度兜底，细粒度在控制器内按帐号计
        ->name('auth.login');

    Route::post('password/forgot', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:10,60')
        ->name('auth.password.forgot');

    Route::post('password/reset', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,60')
        ->name('auth.password.reset');

    // 邮件里的验证连结：signed 中间件校验签名与过期时间
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:10,1'])
        ->name('verification.verify');
});

// 变更邮箱的确认连结寄到新邮箱，此时用户可能未登入，故放在公开区
Route::post('email-change/confirm', [EmailChangeController::class, 'confirm'])
    ->middleware('throttle:10,60')
    ->name('email-change.confirm');

// ── 需登入 ────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [LoginController::class, 'destroy'])->name('auth.logout');

    Route::post('auth/email/resend', [EmailVerificationController::class, 'send'])
        ->name('verification.send');

    Route::prefix('me')->group(function (): void {
        Route::get('/', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('/', [ProfileController::class, 'update'])->name('me.update');

        Route::get('overview', [OverviewController::class, 'index'])->name('me.overview');

        Route::post('avatar', [AvatarController::class, 'update'])
            ->middleware('throttle:20,60')
            ->name('me.avatar.update');
        Route::delete('avatar', [AvatarController::class, 'destroy'])->name('me.avatar.destroy');

        Route::put('password', [PasswordController::class, 'update'])
            ->middleware('throttle:10,60')
            ->name('me.password.update');

        // 变更邮箱要求邮箱已验证：否则等于让未验证帐号随意换入口
        Route::post('email-change', [EmailChangeController::class, 'request'])
            ->middleware(['verified', 'throttle:5,60'])
            ->name('me.email-change');

        Route::get('sessions', [SessionController::class, 'index'])->name('me.sessions');
        Route::delete('sessions/others', [SessionController::class, 'destroyOthers'])
            ->name('me.sessions.others');
        Route::delete('sessions/{id}', [SessionController::class, 'destroy'])
            ->name('me.sessions.destroy');

        Route::get('activities', [ActivityController::class, 'index'])->name('me.activities');
        Route::get('activities/actions', [ActivityController::class, 'actions'])
            ->name('me.activities.actions');
    });
});
