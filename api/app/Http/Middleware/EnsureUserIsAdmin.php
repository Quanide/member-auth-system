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
 * 管理端守衛。
 * 權限判斷只認資料庫裡的 role 欄位——前端選單藏不藏是體驗問題，
 * 真正的邊界在這裡；直接打 API 一樣會被擋下。
 */
final class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            return ApiResponse::error(ErrorCode::FORBIDDEN, '需要管理員權限', 403);
        }

        return $next($request);
    }
}
