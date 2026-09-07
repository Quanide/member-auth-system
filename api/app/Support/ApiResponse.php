<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * 全站统一响应外壳。
 * 成功：{ ok: true, data: ... }
 * 失败：{ ok: false, code: "XXX", message: "...", errors: { 字段: [...] } }
 * 前端只需判断 ok，再按 code 分支处理，不用去猜 HTTP 状态码的语义。
 */
final class ApiResponse
{
    public static function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $data,
        ], $status);
    }

    /** 无返回体的成功操作，带一句可直接展示的提示 */
    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => ['message' => $message],
        ], $status);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $errors = [],
        array $extra = [],
    ): JsonResponse {
        $body = [
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body + $extra, $status);
    }
}
