<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class RegisterController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated());

        // 注册后直接建立会话，省掉一次登入；邮箱验证异步进行不阻断使用
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return ApiResponse::ok([
            'user' => new UserResource($user),
            'message' => '注册成功，验证信已寄至您的邮箱',
        ], 201);
    }
}
