<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理端守卫。
 * 权限判断只认资料库里的 role 栏位——前端选单藏不藏是体验问题，
 * 真正的边界在这里；直接打 API 一样会被挡下。
 */
final class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            return ApiResponse::error(ErrorCode::FORBIDDEN, '需要管理员权限', 403);
        }

        return $next($request);
    }
}
