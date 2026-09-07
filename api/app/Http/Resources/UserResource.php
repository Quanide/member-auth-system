<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),

            'name' => $this->name,
            'nickname' => $this->nickname,
            'display_name' => $this->displayName(),
            'phone' => $this->phone,
            'birthday' => $this->birthday?->toDateString(),
            'gender' => $this->gender,
            'bio' => $this->bio,
            'avatar_url' => $this->avatarUrl(),

            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
