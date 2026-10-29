<?php

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// Web Push（熊叔叔 10/29「Web app 加到主畫面可以做」）。
// 只送「沒有內容」的推播：手機收到後，service worker（public/sw.js）自己回網站抓「您有幾封新紙條」再顯示。
// 這樣不用做推播內容加密（cora-lab／正式站都沒有 gmp、也沒有 composer 可以裝 web-push 套件），只要 VAPID 簽章（openssl ES256）。
// 金鑰在 .env：VAPID_PUBLIC_KEY（base64url 的 65 bytes 公鑰）、VAPID_PRIVATE_KEY（PEM，base64 包一層），用 php artisan push:vapid 產生。
class WebPush
{
    public static function enabled(): bool
    {
        return (string) config('loveuno.push.public_key') !== '' && (string) config('loveuno.push.private_key') !== '';
    }

    // 對這個會員所有的裝置送一則推播；失效的訂閱（404／410）刪掉
    public static function notify(User $user): void
    {
        if (! self::enabled()) {
            return;
        }
        foreach (PushSubscription::where('user_id', $user->id)->get() as $sub) {
            try {
                $res = Http::timeout(10)->withHeaders([
                    'TTL' => '86400',
                    'Urgency' => 'normal',
                    'Authorization' => 'vapid t=' . self::jwt($sub->endpoint) . ', k=' . config('loveuno.push.public_key'),
                ])->withBody('', 'application/octet-stream')->post($sub->endpoint);
                if (in_array($res->status(), [404, 410], true)) {
                    $sub->delete();
                } elseif ($res->failed()) {
                    Log::warning('WebPush: 推播服務回錯誤', ['status' => $res->status(), 'host' => parse_url($sub->endpoint, PHP_URL_HOST)]);
                }
            } catch (Throwable $e) {
                Log::warning('WebPush: 送不出去', ['error' => $e->getMessage(), 'host' => parse_url($sub->endpoint, PHP_URL_HOST)]);
            }
        }
    }

    // VAPID 的 JWT（ES256）：aud＝推播服務的網域、12 小時內有效、sub＝聯絡信箱
    private static function jwt(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $header = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64(json_encode([
            'aud' => $parts['scheme'] . '://' . $parts['host'],
            'exp' => time() + 12 * 3600,
            'sub' => 'mailto:' . config('loveuno.contact_email'),
        ]));
        $data = $header . '.' . $claims;
        $key = openssl_pkey_get_private(base64_decode((string) config('loveuno.push.private_key')));
        openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256);

        return $data . '.' . self::b64(self::derToRaw($der));
    }

    // openssl 簽出來是 DER（SEQUENCE{INTEGER r, INTEGER s}），JWT 要 r||s 各 32 bytes
    private static function derToRaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7F;
        }
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $out .= str_pad(ltrim($int, "\x00"), 32, "\x00", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $out;
    }

    public static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
}
