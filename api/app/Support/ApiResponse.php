<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * 全站統一響應外殼。
 * 成功：{ ok: true, data: ... }
 * 失敗：{ ok: false, code: "XXX", message: "...", errors: { 欄位: [...] } }
 * 前端只需判斷 ok，再按 code 分支處理，不用去猜 HTTP 狀態碼的語義。
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

    /** 無返回體的成功操作，帶一句可直接展示的提示 */
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
