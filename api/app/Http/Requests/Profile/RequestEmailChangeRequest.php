<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

final class RequestEmailChangeRequest extends FormRequest
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
            'new_email' => ['required', 'string', 'email:rfc,filter', 'max:255', 'unique:users,email'],
            // 變更信箱等同於變更帳號入口，必須二次確認身份
            'current_password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'new_email' => '新信箱',
            'current_password' => '目前密碼',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['new_email.unique' => '此信箱已被其他帳號使用'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('new_email'))) {
            $this->merge(['new_email' => mb_strtolower(trim($this->string('new_email')->value()))]);
        }
    }
}
