<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    /** 当前登入者的资料，前端启动时用它判断登入态 */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::ok(['user' => new UserResource($request->user())]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $this->profiles->update($user, $request->validated());

        return ApiResponse::ok([
            'user' => new UserResource($updated),
            'message' => '资料已更新',
        ]);
    }
}
