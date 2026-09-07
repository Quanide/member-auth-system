<?php

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
        // Sanctum SPA 模式：同源请求改用 session cookie 认证，并自动带上 CSRF 校验
        $middleware->statefulApi();

        // 部署在 Nginx 反代之后，需信任代理头才能拿到真实客户端 IP 与 https scheme。
        // 生产环境应把 '*' 收窄为反代的内网地址段。
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 把框架抛出的各类异常统一收敛成 { ok:false, code, message } 结构，
        // 前端只写一套错误处理即可。
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(
                ErrorCode::VALIDATION_FAILED,
                $e->validator->errors()->first() ?: '提交的资料有误',
                422,
                $e->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(ErrorCode::UNAUTHENTICATED, '请先登入', 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(ErrorCode::FORBIDDEN, '没有权限执行此操作', 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(ErrorCode::NOT_FOUND, '找不到指定资源', 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);

            return ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS,
                "操作过于频繁，请于 {$retryAfter} 秒后再试",
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

        // 兜底：未预期的异常一律 500，且绝不把堆栈或 SQL 泄露给客户端
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                return null; // 交给上面已处理的分支或框架默认行为
            }

            report($e);

            return ApiResponse::error(
                ErrorCode::SERVER_ERROR,
                config('app.debug') ? $e->getMessage() : '服务器发生错误，请稍后再试',
                500,
            );
        });
    })->create();
