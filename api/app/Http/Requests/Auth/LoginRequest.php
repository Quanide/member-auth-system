<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
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
            // 登入時只做格式校驗，不加 exists —— 帳號是否存在屬於不該洩露的資訊
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => '信箱',
            'password' => '密碼',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->string('email')->value()))]);
        }
    }

    /** 限流鍵：信箱 + IP，既擋撞庫也擋針對單一帳號的爆破 */
    public function throttleKey(): string
    {
        return mb_strtolower($this->string('email')->value()).'|'.$this->ip();
    }
}
