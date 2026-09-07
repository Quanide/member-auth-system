<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SessionService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SessionController extends Controller
{
    public function __construct(private readonly SessionService $sessions) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok([
            'sessions' => $this->sessions->listFor($user, $request->session()->getId()),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $revoked = $this->sessions->revoke($user, $id, $request->session()->getId());

        if (! $revoked) {
            return ApiResponse::error(ErrorCode::NOT_FOUND, '找不到該登入紀錄，或無法登出目前裝置', 404);
        }

        return ApiResponse::message('該裝置已被登出');
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = $this->sessions->revokeOthers($user, $request->session()->getId());

        return ApiResponse::message("已登出其他 {$count} 個裝置");
    }
}
