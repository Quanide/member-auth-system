<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Models\User;
use App\Services\PasswordService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PasswordController extends Controller
{
    public function __construct(private readonly PasswordService $passwords) {}

    public function update(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->passwords->change(
            $user,
            $request->string('current_password')->value(),
            $request->string('password')->value(),
        );

        // 服务层已踢掉其他装置，当前会话换个 id 继续用
        $request->session()->regenerate();

        return ApiResponse::message('密码已更新，其他装置已被登出');
    }
}
