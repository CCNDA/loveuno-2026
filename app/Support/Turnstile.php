<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Cloudflare Turnstile「我不是機器人」驗證（聯絡我們；熊叔叔 11/6 決定照設計加）
// 沒設 TURNSTILE_SECRET 時不檢查（本機、測試）；測試站用 Cloudflare 官方公開的測試金鑰（永遠通過），正式金鑰由熊叔叔建、Secret 由熊叔叔放 .env
class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function enabled(): bool
    {
        return (string) config('loveuno.turnstile.secret') !== '' && (string) config('loveuno.turnstile.site') !== '';
    }

    // 驗證通過回 true。Cloudflare 連不上時放行（還有隱藏欄位擋機器人、每小時 5 次限制），記一筆 log
    public static function verify(?string $token, ?string $ip): bool
    {
        if (! self::enabled()) {
            return true;
        }
        if ((string) $token === '') {
            return false;
        }
        try {
            $res = Http::asForm()->timeout(8)->connectTimeout(5)->post(self::VERIFY_URL, [
                'secret' => config('loveuno.turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Turnstile 連不上，先放行：' . $e->getMessage());

            return true;
        }
        if (! $res->ok()) {
            Log::warning('Turnstile 回 HTTP ' . $res->status() . '，先放行');

            return true;
        }

        return (bool) $res->json('success');
    }
}
