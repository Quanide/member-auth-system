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

        // 註冊後直接建立會話，省掉一次登入；信箱驗證異步進行不阻斷使用
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return ApiResponse::ok([
            'user' => new UserResource($user),
            'message' => '註冊成功，驗證信已寄至您的信箱',
        ], 201);
    }
}
