<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// 寄簡訊。SMS_DRIVER=log（預設，測試站：只寫進 laravel.log，不花錢）或 twsms（正式站：台灣簡訊，熊叔叔 10/29「沿用」舊站帳號）。
// TwSMS 的寫法照舊站 h2019/sms.php；帳密只放 .env（TWSMS_USERNAME／TWSMS_PASSWORD），不進 git、不印出來。
class Sms
{
    public const TWSMS_SEND_URL = 'https://api.twsms.com/json/sms_send.php';

    // 回傳 ['ok' => bool, 'message' => 給會員看的話（失敗時）]
    public static function send(string $mobile, string $text, int $expirySeconds): array
    {
        $driver = (string) config('loveuno.sms.driver', 'log');
        if ($driver !== 'twsms' && app()->isProduction()) {
            Log::critical('Sms: 正式站的 SMS_DRIVER 不是 twsms，沒有寄出（log 模式會把認證碼寫進記錄檔）');

            return ['ok' => false, 'message' => '簡訊服務還沒設定好，請寫信跟我們聯繫。'];
        }
        if ($driver !== 'twsms') {
            Log::info('簡訊（SMS_DRIVER=log，沒有真的寄出）', ['mobile' => $mobile, 'text' => $text]);

            return ['ok' => true, 'message' => ''];
        }

        $user = (string) config('loveuno.sms.twsms_username');
        $pass = (string) config('loveuno.sms.twsms_password');
        if ($user === '' || $pass === '') {
            Log::error('Sms: TWSMS_USERNAME／TWSMS_PASSWORD 沒有設定，沒有寄出');

            return ['ok' => false, 'message' => '簡訊服務還沒設定好，請寫信跟我們聯繫。'];
        }
        try {
            $res = Http::asForm()->timeout(20)->connectTimeout(10)->post(self::TWSMS_SEND_URL, [
                'username' => $user,
                'password' => $pass,
                'mobile' => $mobile,
                'message' => $text,
                'expirytime' => max(300, min(28800, $expirySeconds)), // TwSMS 允許 300～28800 秒
            ]);
            $code = (string) ($res->json('code') ?? '');
        } catch (Throwable $e) {
            Log::error('Sms: 連不上 TwSMS', ['error' => $e->getMessage()]); // 不記 $user／$pass

            return ['ok' => false, 'message' => '現在連不上簡訊服務，請稍後再試，或寫信跟我們聯繫。'];
        }
        if ($code === '00000') {
            return ['ok' => true, 'message' => ''];
        }
        Log::warning('Sms: TwSMS 回錯誤碼', ['code' => $code, 'mobile' => self::mask($mobile)]);

        return ['ok' => false, 'message' => self::userMessage($code)];
    }

    // 記錄檔裡的手機只留前 4 碼和後 3 碼
    public static function mask(string $mobile): string
    {
        return strlen($mobile) >= 7 ? substr($mobile, 0, 4) . '***' . substr($mobile, -3) : '***';
    }

    // 錯誤碼對給會員看的話（照舊站 twsms_user_message）
    private static function userMessage(string $code): string
    {
        return match ($code) {
            '00020' => '簡訊發送額度不足，請寫信跟我們聯繫。',
            '00031', '00120' => '這個門號目前無法發送認證簡訊，請寫信跟我們聯繫。',
            '00200' => '簡訊發送設定有誤，我們會處理，請稍後再試或寫信跟我們聯繫。',
            default => '簡訊沒有寄出去，請稍後再試，或寫信跟我們聯繫。',
        };
    }
}
