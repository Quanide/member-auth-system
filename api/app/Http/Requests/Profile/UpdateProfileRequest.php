<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'nickname' => ['nullable', 'string', 'max:50'],
            // 兼容大陸 / 臺灣 / 國際號碼，只做寬鬆格式約束
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[+]?[0-9\s\-()]{6,32}$/'],
            'birthday' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'bio' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '姓名',
            'nickname' => '暱稱',
            'phone' => '手機號碼',
            'birthday' => '生日',
            'gender' => '性別',
            'bio' => '個人簡介',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => '手機號碼格式不正確',
            'birthday.before' => '生日必須早於今天',
        ];
    }

    protected function prepareForValidation(): void
    {
        // 前端清空輸入時會送空字符串，統一轉成 null 再入庫，
        // 避免資料庫裡出現 '' 與 NULL 兩種「沒填」的表示。
        $normalized = [];

        foreach (['nickname', 'phone', 'birthday', 'gender', 'bio'] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $normalized[$field] = (is_string($value) && trim($value) === '') ? null : $value;
            }
        }

        if (is_string($this->input('name'))) {
            $normalized['name'] = trim($this->string('name')->value());
        }

        $this->merge($normalized);
    }
}
