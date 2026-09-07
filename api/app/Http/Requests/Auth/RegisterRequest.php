<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'email' => ['required', 'string', 'email:rfc,filter', 'max:255', 'unique:users,email'],
            // confirmed 会自动比对 password_confirmation 字段
            'password' => ['required', 'confirmed', Password::defaults()],
            'agree_terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '姓名',
            'email' => '邮箱',
            'password' => '密码',
            'agree_terms' => '服务条款',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => '此邮箱已被注册，可直接登入或找回密码',
            'agree_terms.accepted' => '请先阅读并同意服务条款',
            'password.confirmed' => '两次输入的密码不一致',
        ];
    }

    protected function prepareForValidation(): void
    {
        // 邮箱统一小写，避免 A@x.com 与 a@x.com 注册出两个帐号
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->string('email')->value()))]);
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->string('name')->value())]);
        }
    }
}
