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
            // confirmed 會自動比對 password_confirmation 欄位
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
            'email' => '信箱',
            'password' => '密碼',
            'agree_terms' => '服務條款',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => '此信箱已被註冊，可直接登入或找回密碼',
            'agree_terms.accepted' => '請先閱讀並同意服務條款',
            'password.confirmed' => '兩次輸入的密碼不一致',
        ];
    }

    protected function prepareForValidation(): void
    {
        // 信箱統一小寫，避免 A@x.com 與 a@x.com 註冊出兩個帳號
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->string('email')->value()))]);
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->string('name')->value())]);
        }
    }
}
