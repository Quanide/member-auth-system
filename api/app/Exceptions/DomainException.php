<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 業務異常基類。
 * 拋出後由框架自動渲染成統一錯誤結構，控制器裡不用層層 if/else 傳錯誤。
 */
class DomainException extends Exception
{
    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $errors = [],
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(
            $this->errorCode,
            $this->getMessage(),
            $this->status,
            $this->errors,
            $this->extra,
        );
    }
}
