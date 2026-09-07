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
     * 會員列表：關鍵字 + 狀態 + 角色篩選。
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

    /** 變更會員狀態（停權 / 恢復） */
    public function updateStatus(User $operator, User $target, UserStatus $status): User
    {
        $this->assertNotSelf($operator, $target, '不能變更自己的帳號狀態');

        $target->forceFill(['status' => $status])->save();

        // 停權後立即清掉該會員所有登入中的會話，否則他手上的 session 還能繼續用
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

    /** 變更會員角色 */
    public function updateRole(User $operator, User $target, UserRole $role): User
    {
        $this->assertNotSelf($operator, $target, '不能變更自己的角色');

        // 避免把最後一個管理員降級，導致沒人能進管理端
        if ($target->isAdmin() && $role !== UserRole::Admin && $this->adminCount() <= 1) {
            throw new DomainException(
                ErrorCode::FORBIDDEN,
                '系統至少需要保留一位管理員',
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

    /** 解除因密碼錯誤過多造成的鎖定 */
    public function unlock(User $operator, User $target): User
    {
        $target->forceFill(['locked_until' => null, 'failed_login_count' => 0])->save();

        $this->audit->log(AuditAction::ProfileUpdated, $operator, [
            'admin_action' => 'unlock',
            'target_user_id' => $target->id,
        ]);

        return $target;
    }

    /** 強制該會員在所有裝置登出 */
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

    /** 軟刪除會員（保留稽核軌跡，可復原） */
    public function delete(User $operator, User $target): void
    {
        $this->assertNotSelf($operator, $target, '不能刪除自己的帳號');

        if ($target->isAdmin() && $this->adminCount() <= 1) {
            throw new DomainException(ErrorCode::FORBIDDEN, '系統至少需要保留一位管理員', 422);
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
