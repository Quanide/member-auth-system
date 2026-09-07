<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Support\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class AdminUserService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * 会员列表：关键字 + 状态 + 角色筛选。
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return User::query()
            ->when(
                filled($filters['keyword'] ?? null),
                function (Builder $query) use ($filters): void {
                    $keyword = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['keyword']).'%';

                    $query->where(function (Builder $inner) use ($keyword): void {
                        $inner->where('email', 'like', $keyword)
                            ->orWhere('name', 'like', $keyword)
                            ->orWhere('nickname', 'like', $keyword)
                            ->orWhere('phone', 'like', $keyword);
                    });
                },
            )
            ->when(filled($filters['status'] ?? null), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(filled($filters['role'] ?? null), fn (Builder $q) => $q->where('role', $filters['role']))
            ->when(
                ($filters['verified'] ?? null) !== null,
                fn (Builder $q) => $filters['verified']
                    ? $q->whereNotNull('email_verified_at')
                    : $q->whereNull('email_verified_at'),
            )
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** 变更会员状态（停权 / 恢复） */
    public function updateStatus(User $operator, User $target, UserStatus $status): User
    {
        $this->assertNotSelf($operator, $target, '不能变更自己的帐号状态');

        $target->forceFill(['status' => $status])->save();

        // 停权后立即清掉该会员所有登入中的会话，否则他手上的 session 还能继续用
        if ($status !== UserStatus::Active) {
            $this->revokeAllSessions($target);
        }

        $this->audit->log(AuditAction::ProfileUpdated, $operator, [
            'admin_action' => 'update_status',
            'target_user_id' => $target->id,
            'status' => $status->value,
        ]);

        return $target;
    }

    /** 变更会员角色 */
    public function updateRole(User $operator, User $target, UserRole $role): User
    {
        $this->assertNotSelf($operator, $target, '不能变更自己的角色');

        // 避免把最后一个管理员降级，导致没人能进管理端
        if ($target->isAdmin() && $role !== UserRole::Admin && $this->adminCount() <= 1) {
            throw new DomainException(
                ErrorCode::FORBIDDEN,
                '系统至少需要保留一位管理员',
                422,
            );
        }

        $target->forceFill(['role' => $role])->save();

        $this->audit->log(AuditAction::ProfileUpdated, $operator, [
            'admin_action' => 'update_role',
            'target_user_id' => $target->id,
            'role' => $role->value,
        ]);

        return $target;
    }

    /** 解除因密码错误过多造成的锁定 */
    public function unlock(User $operator, User $target): User
    {
        $target->forceFill(['locked_until' => null, 'failed_login_count' => 0])->save();

        $this->audit->log(AuditAction::ProfileUpdated, $operator, [
            'admin_action' => 'unlock',
            'target_user_id' => $target->id,
        ]);

        return $target;
    }

    /** 强制该会员在所有装置登出 */
    public function forceLogout(User $operator, User $target): int
    {
        $count = $this->revokeAllSessions($target);

        $this->audit->log(AuditAction::AllSessionsRevoked, $operator, [
            'admin_action' => 'force_logout',
            'target_user_id' => $target->id,
            'count' => $count,
        ]);

        return $count;
    }

    /** 软删除会员（保留稽核轨迹，可复原） */
    public function delete(User $operator, User $target): void
    {
        $this->assertNotSelf($operator, $target, '不能删除自己的帐号');

        if ($target->isAdmin() && $this->adminCount() <= 1) {
            throw new DomainException(ErrorCode::FORBIDDEN, '系统至少需要保留一位管理员', 422);
        }

        $this->revokeAllSessions($target);
        $target->delete();

        $this->audit->log(AuditAction::AccountDeleted, $operator, [
            'admin_action' => 'delete',
            'target_user_id' => $target->id,
            'target_email' => $target->email,
        ]);
    }

    private function assertNotSelf(User $operator, User $target, string $message): void
    {
        if ($operator->id === $target->id) {
            throw new DomainException(ErrorCode::FORBIDDEN, $message, 422);
        }
    }

    private function adminCount(): int
    {
        return User::where('role', UserRole::Admin)->count();
    }

    private function revokeAllSessions(User $user): int
    {
        $count = DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();

        return $count;
    }
}
