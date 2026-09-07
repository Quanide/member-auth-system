<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 會員中心首頁概覽：把「帳號安全狀態」一屏說清楚。
 */
final class OverviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $activeSessions = DB::table('sessions')->where('user_id', $user->id)->count();

        $recentLogins = $user->auditLogs()
            ->where('action', AuditAction::LoginSuccess)
            ->latest('created_at')
            ->limit(5)
            ->get();

        $failedLogins7d = $user->auditLogs()
            ->where('action', AuditAction::LoginFailed)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return ApiResponse::ok([
            'user' => new UserResource($user),
            'stats' => [
                'active_sessions' => $activeSessions,
                'failed_logins_7d' => $failedLogins7d,
                'total_activities' => $user->auditLogs()->count(),
                'account_age_days' => (int) $user->created_at?->diffInDays(now()),
            ],
            'security' => [
                'email_verified' => $user->email_verified_at !== null,
                'has_avatar' => $user->avatar_path !== null,
                'profile_completeness' => $this->completeness($user),
            ],
            'recent_logins' => AuditLogResource::collection($recentLogins),
        ]);
    }

    /** 資料完整度，用來在首頁引導使用者補全 */
    private function completeness(User $user): int
    {
        $fields = [
            $user->name,
            $user->nickname,
            $user->phone,
            $user->birthday,
            $user->gender,
            $user->bio,
            $user->avatar_path,
        ];

        $filled = count(array_filter($fields, fn ($v): bool => $v !== null && $v !== ''));

        return (int) round($filled / count($fields) * 100);
    }
}
