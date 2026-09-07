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
            // 兼容大陆 / 台湾 / 国际号码，只做宽松格式约束
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
            'nickname' => '昵称',
            'phone' => '手机号码',
            'birthday' => '生日',
            'gender' => '性别',
            'bio' => '个人简介',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => '手机号码格式不正确',
            'birthday.before' => '生日必须早于今天',
        ];
    }

    protected function prepareForValidation(): void
    {
        // 前端清空输入时会送空字符串，统一转成 null 再入库，
        // 避免数据库里出现 '' 与 NULL 两种「没填」的表示。
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
