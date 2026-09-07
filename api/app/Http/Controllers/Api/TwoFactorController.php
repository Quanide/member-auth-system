<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    /** 產生密鑰與 QR Code（尚未啟用） */
    public function generate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok($this->twoFactor->generate($user));
    }

    /** 輸入驗證碼確認綁定，回傳一次性恢復碼 */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $codes = $this->twoFactor->confirm($user, $validated['code']);

        return ApiResponse::ok([
            'recovery_codes' => $codes,
            'message' => '雙因素認證已啟用，請妥善保存恢復碼',
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->twoFactor->disable($user, $validated['password']);

        return ApiResponse::message('雙因素認證已關閉');
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $codes = $this->twoFactor->regenerateRecoveryCodes($user, $validated['password']);

        return ApiResponse::ok([
            'recovery_codes' => $codes,
            'message' => '恢復碼已重新產生，舊的已失效',
        ]);
    }
}
