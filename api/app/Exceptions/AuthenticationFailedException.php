<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ErrorCode;

final class AuthenticationFailedException extends DomainException
{
    /** 邮箱不存在与密码错误返回完全相同的响应，避免帐号枚举 */
    public static function invalidCredentials(int $remainingAttempts): self
    {
        return new self(
            ErrorCode::INVALID_CREDENTIALS,
            '邮箱或密码不正确',
            422,
            extra: ['remaining_attempts' => $remainingAttempts],
        );
    }

    public static function locked(int $seconds): self
    {
        $minutes = (int) ceil($seconds / 60);

        return new self(
            ErrorCode::ACCOUNT_LOCKED,
            "密码错误次数过多，帐号已暂时锁定，请于 {$minutes} 分钟后再试",
            423,
            extra: ['retry_after' => $seconds],
        );
    }

    public static function disabled(): self
    {
        return new self(
            ErrorCode::ACCOUNT_DISABLED,
            '此帐号已被停权，请联系客服',
            403,
        );
    }
}
