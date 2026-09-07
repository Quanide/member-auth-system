<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 管理端看板统计。
 *
 * 日期分组统一用 DATE()，MySQL 与 SQLite（测试环境）都支援，
 * 不必为两种驱动各写一份 SQL。
 */
final class AdminStatsService
{
    /** @return array<string, mixed> */
    public function overview(): array
    {
        $today = Carbon::today();

        return [
            'total_users' => User::count(),
            'new_today' => User::whereDate('created_at', $today)->count(),
            'new_7d' => User::where('created_at', '>=', $today->copy()->subDays(7))->count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'locked' => User::whereNotNull('locked_until')->where('locked_until', '>', now())->count(),
            'disabled' => User::where('status', UserStatus::Disabled)->count(),
            // 有活跃 session 的不重复会员数
            'online' => DB::table('sessions')->whereNotNull('user_id')->distinct()->count('user_id'),
            'active_7d' => User::where('last_login_at', '>=', $today->copy()->subDays(7))->count(),
        ];
    }

    /**
     * 注册趋势。补齐没有资料的日期，否则前端折线图会出现断点。
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function registrationTrend(int $days = 30): array
    {
        $since = Carbon::today()->subDays($days - 1);

        $rows = User::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->fillDates($since, $days, fn (string $day): int => (int) ($rows[$day] ?? 0));
    }

    /**
     * 登入成功 / 失败趋势。
     *
     * @return array<string, mixed>
     */
    public function loginTrend(int $days = 14): array
    {
        $since = Carbon::today()->subDays($days - 1);

        $rows = AuditLog::query()
            ->whereIn('action', [AuditAction::LoginSuccess, AuditAction::LoginFailed])
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, action, COUNT(*) as total')
            ->groupBy('day', 'action')
            ->get();

        $success = $rows->where('action', AuditAction::LoginSuccess)->pluck('total', 'day');
        $failed = $rows->where('action', AuditAction::LoginFailed)->pluck('total', 'day');

        return [
            'dates' => $this->fillDates($since, $days, fn (string $day): int => 0, dateOnly: true),
            'success' => $this->fillDates($since, $days, fn (string $day): int => (int) ($success[$day] ?? 0), valuesOnly: true),
            'failed' => $this->fillDates($since, $days, fn (string $day): int => (int) ($failed[$day] ?? 0), valuesOnly: true),
        ];
    }

    /**
     * 会员状态分布，供圆环图使用。
     *
     * @return array<int, array{name: string, value: int}>
     */
    public function statusDistribution(): array
    {
        $counts = User::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_values(array_filter(
            array_map(
                fn (UserStatus $status): array => [
                    'name' => $status->label(),
                    'value' => (int) ($counts[$status->value] ?? 0),
                ],
                UserStatus::cases(),
            ),
            fn (array $item): bool => $item['value'] > 0,
        ));
    }

    /**
     * 登入装置分布，从稽核日志的 User-Agent 粗略归类。
     *
     * @return array<int, array{name: string, value: int}>
     */
    public function deviceDistribution(): array
    {
        $agents = AuditLog::query()
            ->where('action', AuditAction::LoginSuccess)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('user_agent')
            ->pluck('user_agent');

        $buckets = [];

        foreach ($agents as $agent) {
            $os = match (true) {
                str_contains($agent, 'Windows') => 'Windows',
                str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
                str_contains($agent, 'Android') => 'Android',
                str_contains($agent, 'Mac OS X') => 'macOS',
                str_contains($agent, 'Linux') => 'Linux',
                default => '其他',
            };

            $buckets[$os] = ($buckets[$os] ?? 0) + 1;
        }

        arsort($buckets);

        return array_map(
            fn (string $name, int $value): array => ['name' => $name, 'value' => $value],
            array_keys($buckets),
            array_values($buckets),
        );
    }

    /**
     * 补齐日期区间。
     *
     * @param  callable(string): int  $resolver
     * @return array<int, mixed>
     */
    private function fillDates(
        Carbon $since,
        int $days,
        callable $resolver,
        bool $dateOnly = false,
        bool $valuesOnly = false,
    ): array {
        $result = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $since->copy()->addDays($i)->toDateString();

            $result[] = match (true) {
                $dateOnly => $day,
                $valuesOnly => $resolver($day),
                default => ['date' => $day, 'count' => $resolver($day)],
            };
        }

        return $result;
    }
}
