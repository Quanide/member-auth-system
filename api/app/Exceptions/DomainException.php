<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 业务异常基类。
 * 抛出后由框架自动渲染成统一错误结构，控制器里不用层层 if/else 传错误。
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
