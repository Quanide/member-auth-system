<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    /** 正常 */
    case Active = 'active';
    /** 密码连续错误被系统临时锁定 */
    case Locked = 'locked';
    /** 管理员停权 */
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active => '正常',
            self::Locked => '已锁定',
            self::Disabled => '已停权',
        };
    }

    /** 能否登入 */
    public function canLogin(): bool
    {
        return $this === self::Active;
    }
}
