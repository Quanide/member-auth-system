<?php

declare(strict_types=1);

/**
 * 验证错误的中文文案。
 * 只收录本项目实际用到的规则，避免搬一份几百行的全量翻译进来当死代码。
 */
return [
    'accepted' => '请同意 :attribute。',
    'after' => ':attribute 必须晚于 :date。',
    'before' => ':attribute 必须早于 :date。',
    'boolean' => ':attribute 只能是 true 或 false。',
    'confirmed' => ':attribute 两次输入不一致。',
    'current_password' => '密码不正确。',
    'date' => ':attribute 不是有效的日期格式。',
    'different' => ':attribute 与 :other 不能相同。',
    'email' => ':attribute 格式不正确。',
    'file' => ':attribute 必须是文件。',
    'image' => ':attribute 必须是图片。',
    'in' => '所选的 :attribute 无效。',
    'integer' => ':attribute 必须是整数。',
    'max' => [
        'array' => ':attribute 最多只能有 :max 项。',
        'file' => ':attribute 不能大于 :max KB。',
        'numeric' => ':attribute 不能大于 :max。',
        'string' => ':attribute 不能超过 :max 个字符。',
    ],
    'mimes' => ':attribute 必须是 :values 格式的文件。',
    'min' => [
        'array' => ':attribute 至少要有 :min 项。',
        'file' => ':attribute 不能小于 :min KB。',
        'numeric' => ':attribute 不能小于 :min。',
        'string' => ':attribute 至少需要 :min 个字符。',
    ],
    'regex' => ':attribute 格式不正确。',
    'required' => '请填写 :attribute。',
    'string' => ':attribute 必须是文字。',
    'unique' => ':attribute 已经被使用。',
    'uploaded' => ':attribute 上传失败，请重试。',

    'password' => [
        'letters' => ':attribute 必须包含至少一个字母。',
        'mixed' => ':attribute 必须同时包含大写和小写字母。',
        'numbers' => ':attribute 必须包含至少一个数字。',
        'symbols' => ':attribute 必须包含至少一个符号。',
        'uncompromised' => '此 :attribute 曾出现在已知的资料外泄事件中，请改用其他密码。',
    ],

    'attributes' => [
        'name' => '姓名',
        'nickname' => '昵称',
        'email' => '邮箱',
        'new_email' => '新邮箱',
        'password' => '密码',
        'current_password' => '目前密码',
        'password_confirmation' => '确认密码',
        'phone' => '手机号码',
        'birthday' => '生日',
        'gender' => '性别',
        'bio' => '个人简介',
        'avatar' => '头像',
        'token' => '验证码',
        'agree_terms' => '服务条款',
    ],
];
