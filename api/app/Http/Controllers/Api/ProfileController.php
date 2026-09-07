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

    /** 當前登入者的資料，前端啟動時用它判斷登入態 */
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
            'message' => '資料已更新',
        ]);
    }
}
