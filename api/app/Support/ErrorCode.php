<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 前后端约定的错误码。
 * 用常量而非魔法字符串，改名时 IDE 能全局重构，也方便前端对照。
 */
final class ErrorCode
{
    public const VALIDATION_FAILED = 'VALIDATION_FAILED';

    public const UNAUTHENTICATED = 'UNAUTHENTICATED';

    public const FORBIDDEN = 'FORBIDDEN';

    public const NOT_FOUND = 'NOT_FOUND';

    public const TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';

    public const SERVER_ERROR = 'SERVER_ERROR';

    public const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';

    public const ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';

    public const ACCOUNT_DISABLED = 'ACCOUNT_DISABLED';

    public const EMAIL_NOT_VERIFIED = 'EMAIL_NOT_VERIFIED';

    public const EMAIL_ALREADY_VERIFIED = 'EMAIL_ALREADY_VERIFIED';

    public const INVALID_TOKEN = 'INVALID_TOKEN';

    public const PASSWORD_MISMATCH = 'PASSWORD_MISMATCH';

    public const EMAIL_TAKEN = 'EMAIL_TAKEN';
}
