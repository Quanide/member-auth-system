<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AvatarService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AvatarController extends Controller
{
    public function __construct(private readonly AvatarService $avatars) {}

    public function update(UpdateAvatarRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->avatars->update($user, $request->file('avatar'));

        return ApiResponse::ok([
            'user' => new UserResource($user->refresh()),
            'message' => '头像已更新',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->avatars->remove($user);

        return ApiResponse::ok([
            'user' => new UserResource($user->refresh()),
            'message' => '头像已移除',
        ]);
    }
}
