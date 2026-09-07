<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 全站稽核日誌檢索（跨會員）。
 */
final class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'user_id' => ['nullable', 'integer'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = AuditLog::query()
            // eager load 避免列表逐筆查使用者造成 N+1
            ->with('user:id,name,nickname,email')
            ->when(filled($filters['action'] ?? null), fn (Builder $q) => $q->where('action', $filters['action']))
            ->when(filled($filters['user_id'] ?? null), fn (Builder $q) => $q->where('user_id', $filters['user_id']))
            ->when(
                filled($filters['keyword'] ?? null),
                function (Builder $q) use ($filters): void {
                    $keyword = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['keyword']).'%';

                    $q->where('ip_address', 'like', $keyword)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', $keyword));
                },
            )
            ->when(filled($filters['from'] ?? null), fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 20));

        return ApiResponse::ok([
            'items' => array_map(
                fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action->value,
                    'action_label' => $log->action->label(),
                    'level' => $log->action->level(),
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'meta' => $log->meta,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'user' => $log->user === null ? null : [
                        'id' => $log->user->id,
                        'email' => $log->user->email,
                        'display_name' => $log->user->displayName(),
                    ],
                ],
                $page->items(),
            ),
            'pagination' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
