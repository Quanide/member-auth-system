<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class UserController extends Controller
{
    public function __construct(private readonly AdminUserService $users) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'verified' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = $this->users->paginate($filters);

        return ApiResponse::ok([
            'items' => UserResource::collection($page->items()),
            'pagination' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /** 會員詳情：附帶登入裝置與最近操作，方便管理員排查問題 */
    public function show(User $user): JsonResponse
    {
        return ApiResponse::ok([
            'user' => new UserResource($user),
            'sessions_count' => DB::table('sessions')->where('user_id', $user->id)->count(),
            'recent_activities' => AuditLogResource::collection(
                $user->auditLogs()->latest('created_at')->limit(20)->get(),
            ),
        ]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        $updated = $this->users->updateStatus(
            $request->user(),
            $user,
            UserStatus::from($validated['status']),
        );

        return ApiResponse::ok([
            'user' => new UserResource($updated),
            'message' => '會員狀態已更新',
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $updated = $this->users->updateRole(
            $request->user(),
            $user,
            UserRole::from($validated['role']),
        );

        return ApiResponse::ok([
            'user' => new UserResource($updated),
            'message' => '會員角色已更新',
        ]);
    }

    public function unlock(Request $request, User $user): JsonResponse
    {
        $updated = $this->users->unlock($request->user(), $user);

        return ApiResponse::ok([
            'user' => new UserResource($updated),
            'message' => '已解除鎖定',
        ]);
    }

    public function forceLogout(Request $request, User $user): JsonResponse
    {
        $count = $this->users->forceLogout($request->user(), $user);

        return ApiResponse::message("已強制登出 {$count} 個裝置");
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->users->delete($request->user(), $user);

        return ApiResponse::message('會員已刪除');
    }
}
