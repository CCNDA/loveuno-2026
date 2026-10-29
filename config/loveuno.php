<?php

return [
    // 開放註冊。11/8 會員大會是展示版，不開放；年底正式開放時才打開（熊叔叔 10/29：預計年底）
    'registration_open' => (bool) env('REGISTRATION_OPEN', false),

    // 簡訊（手機認證）：log＝只寫進 laravel.log（測試站）；twsms＝台灣簡訊（正式站，熊叔叔 10/29「沿用」舊站帳號）
    // 🔴 正式站一定要設 twsms：log 模式會把認證碼寫進記錄檔
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'twsms_username' => env('TWSMS_USERNAME'),
        'twsms_password' => env('TWSMS_PASSWORD'),
    ],

    // Web Push 的 VAPID 金鑰（php artisan push:vapid 產生、寫進 .env；沒有就不送推播）
    'push' => [
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],

    // 會員收不到簡訊等問題要寫信來的地方（熊叔叔 10/29「愛諾園有愛諾園的專屬信箱 support@loveuno.com」）
    'contact_email' => env('LOVEUNO_CONTACT_EMAIL', 'support@loveuno.com'),
];
