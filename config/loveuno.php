<?php

return [
    // 對外連結：Nien Chen 2023-12-23 在 Figma（Index2、menu、Register）留的轉址短網址，轉到正確網址、帶 loveuno 來源追蹤（熊叔叔 2026-11-05 TG）
    'links' => [
        'devote' => 'https://go.nien.co/3LUoTeH',      // 協會奉獻頁
        'ccnda' => 'https://go.nien.co/3pbaRwa',       // 網路發展協會
        'church' => 'https://go.nien.co/46Lofrx',      // 教會名錄
        'oursweb' => 'https://bit.ly/3GXGAqx',         // Oursweb
        'taiwanbible' => 'https://go.nien.co/3LVa7o7', // 台灣聖經網
        'facebook' => 'https://go.hearty.me/ccnda-fb',
        'line' => 'https://go.nien.co/3VJaTYk',        // LINE 官方帳號
        'telegram' => 'https://go.nien.co/42yZOeN',
        'google_review' => 'https://go.hearty.me/ccnda-gb', // 設計稿的星星：協會 Google 評論
        'instagram' => 'https://go.hearty.me/ccnda-ig',     // Nien Chen「少了 IG 和 Messenger」（熊叔叔 11/5「要加，都是用協會的」）
        'messenger' => 'https://m.me/100064814103255',
    ],

    // 開放註冊。11/8 會員大會是展示版，不開放；年底正式開放時才打開（熊叔叔 10/29：預計年底）
    'registration_open' => (bool) env('REGISTRATION_OPEN', false),

    // 公測邀請制（熊叔叔 11/16 選乙；Asana 1220564012971609）：開著時只有名單上的人（users.beta_invited_at）和管理員能登入
    // 名單用 php artisan beta:invite 管理。測試站、展示版不設＝關
    'beta_invite_only' => (bool) env('BETA_INVITE_ONLY', false),

    // 公測停寫開關（熊叔叔 11/17 選甲；Asana 1220586286066431）：開著時只能看、不能寫（登入登出除外）
    // 改 .env 後 php artisan config:clear（有 config:cache 就重跑 config:cache）
    'beta_readonly' => (bool) env('BETA_READONLY', false),

    // 公測寫入紀錄＋回寫待送清單（熊叔叔 11/17 選甲、11/18 選乙）：公測站開，測試站、展示版不設＝關
    'beta_audit' => (bool) env('BETA_AUDIT', false),
    // 寫入紀錄只留幾天（存整筆欄位，含 email、手機；小布 11/21 審查 🟡6）。每天 03:40 php artisan beta:prune-audits
    'beta_audit_keep_days' => (int) env('BETA_AUDIT_KEEP_DAYS', 60),

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
    // 愛的見證照片自動找臉：Python（裝了 opencv）和 face_focus.py 的路徑；任一個不存在就不找（用預設焦點）
    'face' => [
        'python' => env('LOVEUNO_FACE_PYTHON', '/opt/loveuno-face/venv/bin/python'),
        'script' => env('LOVEUNO_FACE_SCRIPT', '/opt/loveuno-face/face_focus.py'),
    ],

    // 名錄公開的機構清單（每天同步教會，自我介紹選教會用；熊叔叔 11/6 決定 #11 甲）
    'directory_churches_url' => env('DIRECTORY_CHURCHES_URL', 'https://church.oursweb.net/data/church.json'),

    // 聯絡我們的「我不是機器人」（Cloudflare Turnstile）：兩個都有值才檢查。Secret 不進 git、不經過信／TG
    'turnstile' => [
        'site' => env('TURNSTILE_SITE_KEY', ''),
        'secret' => env('TURNSTILE_SECRET', ''),
    ],

    'contact_email' => env('LOVEUNO_CONTACT_EMAIL', 'support@loveuno.com'),
];
