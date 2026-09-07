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

    /** 产生密钥与 QR Code（尚未启用） */
    public function generate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok($this->twoFactor->generate($user));
    }

    /** 输入验证码确认绑定，回传一次性恢复码 */
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
            'message' => '双因素认证已启用，请妥善保存恢复码',
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

        return ApiResponse::message('双因素认证已关闭');
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
            'message' => '恢复码已重新产生，旧的已失效',
        ]);
    }
}
