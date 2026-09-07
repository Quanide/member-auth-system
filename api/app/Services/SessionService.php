<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 登入裝置管理。
 * session driver 設為 database，才能把「目前有哪些裝置登入著」列出來並逐一撤銷。
 */
final class SessionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listFor(User $user, string $currentSessionId): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $row): array => [
                // 不把完整 session id 發給前端，截斷後僅用於頁面上區分不同裝置
                'id' => substr($row->id, 0, 12),
                'is_current' => $row->id === $currentSessionId,
                'ip_address' => $row->ip_address,
                'device' => $this->describeDevice((string) $row->user_agent),
                'user_agent' => $row->user_agent,
                'last_active_at' => Carbon::createFromTimestamp($row->last_activity)->toIso8601String(),
            ]);
    }

    /** 撤銷單一裝置（按截斷後的前綴匹配） */
    public function revoke(User $user, string $sessionIdPrefix, string $currentSessionId): bool
    {
        $deleted = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId) // 不允許把自己踢掉，避免誤操作
            ->where('id', 'like', $sessionIdPrefix.'%')
            ->delete();

        if ($deleted > 0) {
            $this->audit->log(AuditAction::SessionRevoked, $user);
        }

        return $deleted > 0;
    }

    /** 登出其他所有裝置 */
    public function revokeOthers(User $user, string $currentSessionId): int
    {
        $count = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        $user->tokens()->delete();

        if ($count > 0) {
            $this->audit->log(AuditAction::AllSessionsRevoked, $user, ['count' => $count]);
        }

        return $count;
    }

    /** 從 UA 粗略解析裝置資訊，僅供使用者辨認，不做精確指紋 */
    private function describeDevice(string $userAgent): string
    {
        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => '未知系統',
        };

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') && ! str_contains($userAgent, 'Chromium') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') && ! str_contains($userAgent, 'Chrome') => 'Safari',
            default => '未知瀏覽器',
        };

        return "{$os} · {$browser}";
    }
}
