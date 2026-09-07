<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ActivityController extends Controller
{
    /** 會員查看自己的操作紀錄 */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $logs = $user->auditLogs()
            ->when(
                isset($validated['action']),
                fn ($query) => $query->where('action', $validated['action']),
            )
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 20);

        return ApiResponse::ok([
            'items' => AuditLogResource::collection($logs->items()),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /** 供前端篩選下拉框使用 */
    public function actions(): JsonResponse
    {
        return ApiResponse::ok([
            'actions' => array_map(
                fn (AuditAction $a): array => [
                    'value' => $a->value,
                    'label' => $a->label(),
                    'level' => $a->level(),
                ],
                AuditAction::cases(),
            ),
        ]);
    }
}
