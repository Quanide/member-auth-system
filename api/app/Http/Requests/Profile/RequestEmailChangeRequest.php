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
            // 变更邮箱等同于变更帐号入口，必须二次确认身份
            'current_password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'new_email' => '新邮箱',
            'current_password' => '目前密码',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['new_email.unique' => '此邮箱已被其他帐号使用'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('new_email'))) {
            $this->merge(['new_email' => mb_strtolower(trim($this->string('new_email')->value()))]);
        }
    }
}
