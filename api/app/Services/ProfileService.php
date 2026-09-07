<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\User;

final class ProfileService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * 更新會員資料。
     * 只記錄「真正發生變化」的欄位名到審計日誌——不記錄值本身，
     * 避免把手機號、生日這類個資明文堆進日誌表。
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->fill($data);
        $changed = array_keys($user->getDirty());

        if ($changed === []) {
            return $user;
        }

        $user->save();

        $this->audit->log(AuditAction::ProfileUpdated, $user, ['fields' => $changed]);

        return $user;
    }
}
