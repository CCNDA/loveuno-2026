<?php

return [
    // 開放註冊。11/8 會員大會是展示版，不開放；年底正式開放時才打開（熊叔叔 10/29：預計年底）
    'registration_open' => (bool) env('REGISTRATION_OPEN', false),
];
