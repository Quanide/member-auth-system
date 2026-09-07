<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\RequestEmailChangeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\EmailChangeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmailChangeController extends Controller
{
    public function __construct(private readonly EmailChangeService $emailChange) {}

    public function request(RequestEmailChangeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->emailChange->request(
            $user,
            $request->string('new_email')->value(),
            $request->string('current_password')->value(),
        );

        return ApiResponse::message('验证信已寄至新邮箱，请于 60 分钟内点击连结完成变更');
    }

    /** 新邮箱收到的连结会带 token 打到这里，无需登入态即可完成 */
    public function confirm(Request $request): JsonResponse
    {
        $token = $request->string('token')->value();

        $user = $this->emailChange->confirm($token);

        return ApiResponse::ok([
            'user' => new UserResource($user),
            'message' => '邮箱变更成功',
        ]);
    }
}
