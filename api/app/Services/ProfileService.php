<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\User;

final class ProfileService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * 更新会员资料。
     * 只记录「真正发生变化」的字段名到审计日志——不记录值本身，
     * 避免把手机号、生日这类个资明文堆进日志表。
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
