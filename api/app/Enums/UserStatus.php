<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    /** 正常 */
    case Active = 'active';
    /** 密碼連續錯誤被系統臨時鎖定 */
    case Locked = 'locked';
    /** 管理員停權 */
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active => '正常',
            self::Locked => '已鎖定',
            self::Disabled => '已停權',
        };
    }

    /** 能否登入 */
    public function canLogin(): bool
    {
        return $this === self::Active;
    }
}
