<?php

// 表單錯誤訊息（APP_LOCALE=zh_TW）。0.15.0 以前沒有這個檔，沒有自訂文字的訊息會顯示英文
// （例如「The 內容描述 field is required.」）——檢舉功能兩面審查 10/29 發現。
// 只放本站用到的規則；欄位名稱由各 controller 的 attributes 帶進來（:attribute）。
return [
    'accepted' => '請勾選:attribute。',
    'between' => [
        'numeric' => ':attribute要在 :min 到 :max 之間。',
        'string' => ':attribute要在 :min 到 :max 個字之間。',
    ],
    'boolean' => ':attribute的值不正確。',
    'confirmed' => '兩次輸入的:attribute不一樣。',
    'current_password' => '密碼不正確。',
    'date' => ':attribute不是正確的日期。',
    'dimensions' => ':attribute的尺寸不符合規定。',
    'email' => '請輸入正確的電子郵件，例如 name@example.com。',
    'file' => ':attribute要是一個檔案。',
    'gte' => [
        'numeric' => ':attribute要大於或等於 :value。',
    ],
    'image' => ':attribute要是圖片。',
    'in' => '請從選項裡選一個:attribute。',
    'integer' => ':attribute要是整數。',
    'max' => [
        'file' => ':attribute不能超過 :max KB。',
        'numeric' => ':attribute不能大於 :max。',
        'string' => ':attribute不能超過 :max 個字。',
    ],
    'min' => [
        'numeric' => ':attribute不能小於 :min。',
        'string' => ':attribute至少要 :min 個字元。',
    ],
    'mimes' => ':attribute的檔案格式要是 :values。',
    'required' => '請填寫:attribute。',
    'string' => ':attribute要是文字。',
    'uploaded' => ':attribute上傳失敗，請再試一次。',

    'attributes' => [],
];
