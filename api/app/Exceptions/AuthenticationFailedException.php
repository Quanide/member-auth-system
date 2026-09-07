<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ErrorCode;

final class AuthenticationFailedException extends DomainException
{
    /** 信箱不存在與密碼錯誤返回完全相同的響應，避免帳號枚舉 */
    public static function invalidCredentials(int $remainingAttempts): self
    {
        return new self(
            ErrorCode::INVALID_CREDENTIALS,
            '信箱或密碼不正確',
            422,
            extra: ['remaining_attempts' => $remainingAttempts],
        );
    }

    public static function locked(int $seconds): self
    {
        $minutes = (int) ceil($seconds / 60);

        return new self(
            ErrorCode::ACCOUNT_LOCKED,
            "密碼錯誤次數過多，帳號已暫時鎖定，請於 {$minutes} 分鐘後再試",
            423,
            extra: ['retry_after' => $seconds],
        );
    }

    public static function disabled(): self
    {
        return new self(
            ErrorCode::ACCOUNT_DISABLED,
            '此帳號已被停權，請聯繫客服',
            403,
        );
    }
}
