<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 這裡只是第一道；AvatarService 會再用 getimagesize 讀檔頭做真實型別校驗
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['avatar' => '頭像'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.max' => '頭像檔案不能超過 5 MB',
            'avatar.mimes' => '頭像僅支援 JPG、PNG、WebP 格式',
        ];
    }
}
