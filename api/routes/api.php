<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\Admin\AuditController as AdminAuditController;
use App\Http\Controllers\Api\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
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
use App\Http\Controllers\Api\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API 路由
|--------------------------------------------------------------------------
| 認證走 Sanctum 的 SPA 模式：前後端同域部署，憑證放在 httpOnly Cookie 裡，
| 前端 JS 讀不到 token，配合 CSRF token 抵禦跨站請求偽造。
*/

// ── 訪客可用 ──────────────────────────────────────────────
Route::prefix('auth')->group(function (): void {
    Route::post('register', [RegisterController::class, 'store'])
        ->middleware('throttle:6,60')          // 同 IP 每小時最多 6 次註冊
        ->name('auth.register');

    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:20,1')          // 粗粒度兜底，細粒度在控制器內按帳號計
        ->name('auth.login');

    Route::post('password/forgot', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:10,60')
        ->name('auth.password.forgot');

    Route::post('password/reset', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,60')
        ->name('auth.password.reset');

    // 雙因素第二關：密碼已通過但尚未建立登入狀態，因此放在公開區
    Route::post('two-factor-challenge', [LoginController::class, 'twoFactorChallenge'])
        ->middleware('throttle:20,1')
        ->name('auth.two-factor.challenge');

    // 郵件裡的驗證連結：signed 中間件校驗籤名與過期時間
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:10,1'])
        ->name('verification.verify');
});

// 變更信箱的確認連結寄到新信箱，此時使用者可能未登入，故放在公開區
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

        // 變更信箱要求信箱已驗證：否則等於讓未驗證帳號隨意換入口
        Route::post('email-change', [EmailChangeController::class, 'request'])
            ->middleware(['verified', 'throttle:5,60'])
            ->name('me.email-change');

        // 雙因素認證
        Route::prefix('two-factor')->group(function (): void {
            Route::post('generate', [TwoFactorController::class, 'generate'])
                ->middleware('throttle:10,60')
                ->name('me.2fa.generate');
            Route::post('confirm', [TwoFactorController::class, 'confirm'])
                ->middleware('throttle:10,10')
                ->name('me.2fa.confirm');
            Route::post('disable', [TwoFactorController::class, 'disable'])
                ->middleware('throttle:10,60')
                ->name('me.2fa.disable');
            Route::post('recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])
                ->middleware('throttle:5,60')
                ->name('me.2fa.recovery-codes');
        });

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

// ── 管理端（需 admin 角色）────────────────────────────────
// 權限由 middleware 把關，與前端選單是否顯示無關
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('stats', [AdminStatsController::class, 'index'])->name('admin.stats');

    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('users/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
    Route::patch('users/{user}/status', [AdminUserController::class, 'updateStatus'])
        ->name('admin.users.status');
    Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])
        ->name('admin.users.role');
    Route::post('users/{user}/unlock', [AdminUserController::class, 'unlock'])
        ->name('admin.users.unlock');
    Route::post('users/{user}/force-logout', [AdminUserController::class, 'forceLogout'])
        ->name('admin.users.force-logout');
    Route::delete('users/{user}', [AdminUserController::class, 'destroy'])
        ->name('admin.users.destroy');

    Route::get('audit-logs', [AdminAuditController::class, 'index'])->name('admin.audit-logs');
});
