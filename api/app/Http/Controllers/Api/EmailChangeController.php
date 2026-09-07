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

        return ApiResponse::message('驗證信已寄至新信箱，請於 60 分鐘內點擊連結完成變更');
    }

    /** 新信箱收到的連結會帶 token 打到這裡，無需登入態即可完成 */
    public function confirm(Request $request): JsonResponse
    {
        $token = $request->string('token')->value();

        $user = $this->emailChange->confirm($token);

        return ApiResponse::ok([
            'user' => new UserResource($user),
            'message' => '信箱變更成功',
        ]);
    }
}
