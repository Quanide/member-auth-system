<?php

declare(strict_types=1);

/**
 * 驗證錯誤的中文文案。
 * 只收錄本專案實際用到的規則，避免搬一份幾百行的全量翻譯進來當死程式碼。
 */
return [
    'accepted' => '請同意 :attribute。',
    'after' => ':attribute 必須晚於 :date。',
    'before' => ':attribute 必須早於 :date。',
    'boolean' => ':attribute 只能是 true 或 false。',
    'confirmed' => ':attribute 兩次輸入不一致。',
    'current_password' => '密碼不正確。',
    'date' => ':attribute 不是有效的日期格式。',
    'different' => ':attribute 與 :other 不能相同。',
    'email' => ':attribute 格式不正確。',
    'file' => ':attribute 必須是檔案。',
    'image' => ':attribute 必須是圖片。',
    'in' => '所選的 :attribute 無效。',
    'integer' => ':attribute 必須是整數。',
    'max' => [
        'array' => ':attribute 最多只能有 :max 項。',
        'file' => ':attribute 不能大於 :max KB。',
        'numeric' => ':attribute 不能大於 :max。',
        'string' => ':attribute 不能超過 :max 個字符。',
    ],
    'mimes' => ':attribute 必須是 :values 格式的檔案。',
    'min' => [
        'array' => ':attribute 至少要有 :min 項。',
        'file' => ':attribute 不能小於 :min KB。',
        'numeric' => ':attribute 不能小於 :min。',
        'string' => ':attribute 至少需要 :min 個字符。',
    ],
    'regex' => ':attribute 格式不正確。',
    'required' => '請填寫 :attribute。',
    'string' => ':attribute 必須是文字。',
    'unique' => ':attribute 已經被使用。',
    'uploaded' => ':attribute 上傳失敗，請重試。',

    'password' => [
        'letters' => ':attribute 必須包含至少一個字母。',
        'mixed' => ':attribute 必須同時包含大寫和小寫字母。',
        'numbers' => ':attribute 必須包含至少一個數字。',
        'symbols' => ':attribute 必須包含至少一個符號。',
        'uncompromised' => '此 :attribute 曾出現在已知的資料外洩事件中，請改用其他密碼。',
    ],

    'attributes' => [
        'name' => '姓名',
        'nickname' => '暱稱',
        'email' => '信箱',
        'new_email' => '新信箱',
        'password' => '密碼',
        'current_password' => '目前密碼',
        'password_confirmation' => '確認密碼',
        'phone' => '手機號碼',
        'birthday' => '生日',
        'gender' => '性別',
        'bio' => '個人簡介',
        'avatar' => '頭像',
        'token' => '驗證碼',
        'agree_terms' => '服務條款',
    ],
];
