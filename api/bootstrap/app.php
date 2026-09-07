<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA 模式：同源請求改用 session cookie 認證，並自動帶上 CSRF 校驗
        $middleware->statefulApi();

        // 部署在 Nginx 反代之後，需信任代理頭才能拿到真實用戶端 IP 與 https scheme。
        // 生產環境應把 '*' 收窄為反代的內網地址段。
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 把框架拋出的各類異常統一收斂成 { ok:false, code, message } 結構，
        // 前端只寫一套錯誤處理即可。
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(
                ErrorCode::VALIDATION_FAILED,
                $e->validator->errors()->first() ?: '提交的資料有誤',
                422,
                $e->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(ErrorCode::UNAUTHENTICATED, '請先登入', 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(ErrorCode::FORBIDDEN, '沒有權限執行此操作', 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(ErrorCode::NOT_FOUND, '找不到指定資源', 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);

            return ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS,
                "操作過於頻繁，請於 {$retryAfter} 秒後再試",
                429,
                extra: ['retry_after' => $retryAfter],
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(ErrorCode::NOT_FOUND, '接口不存在', 404);
        });

        // 兜底：未預期的異常一律 500，且絕不把堆棧或 SQL 洩露給用戶端
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                return null; // 交給上面已處理的分支或框架預設行為
            }

            report($e);

            return ApiResponse::error(
                ErrorCode::SERVER_ERROR,
                config('app.debug') ? $e->getMessage() : '伺服器發生錯誤，請稍後再試',
                500,
            );
        });
    })->create();
