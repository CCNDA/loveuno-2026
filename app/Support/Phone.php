<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

// 手機號碼與認證期限，照舊站 h2019/phone_util.php（熊叔叔 10/29「2a 都收 2b照舊站 2c一支手機 一個帳號」）。
// users.phone 存的格式也跟舊站 profile.mobilephone 一樣：台灣「09xxxxxxxx」，國外「+國碼-門號」（門號去掉開頭的 0）。
// 同一支手機只會有一種寫法，所以 users.phone 的唯一索引就能擋「一支手機一個帳號」。
class Phone
{
    public const VALID_MONTHS = 12; // 舊站 PHONE_CERT_VALID_MONTHS：認證日起 12 個月，過了要重新認證

    // 舊站 phone_country_list()
    public const COUNTRIES = [
        '886' => '台灣 +886', '852' => '香港 +852', '853' => '澳門 +853', '86' => '中國大陸 +86',
        '65' => '新加坡 +65', '60' => '馬來西亞 +60', '81' => '日本 +81', '82' => '韓國 +82',
        '63' => '菲律賓 +63', '62' => '印尼 +62', '66' => '泰國 +66', '84' => '越南 +84',
        '91' => '印度 +91', '1' => '美國／加拿大 +1', '61' => '澳洲 +61', '64' => '紐西蘭 +64',
        '44' => '英國 +44', '49' => '德國 +49', '33' => '法國 +33',
    ];

    // 舊站 phone_country_from_live_place()：居住地是國外的，國碼先幫忙選好
    private const PLACE_COUNTRY = [
        '香港' => '852', '澳門' => '853', '中國大陸' => '86', '新加坡' => '65', '馬來西亞' => '60', '日本' => '81',
        '韓國' => '82', '菲律賓' => '63', '印尼' => '62', '泰國' => '66', '印度' => '91', '美國' => '1',
        '加拿大' => '1', '北美洲' => '1', '澳洲' => '61', '紐西蘭' => '64',
    ];

    public static function countryFromPlace(?string $place): string
    {
        return self::PLACE_COUNTRY[trim((string) $place)] ?? '886';
    }

    // 檢查格式（照舊站 phone_validate 的規則與用字）。通過回 [存進資料庫的寫法, null]，不通過回 [null, 錯誤訊息]
    public static function normalize(string $country, string $mobile): array
    {
        $mobile = preg_replace('/[\s-]/', '', $mobile);
        if (! isset(self::COUNTRIES[$country])) {
            return [null, '請選擇國碼。'];
        }
        if ($mobile === '') {
            return [null, '請輸入手機號碼。'];
        }
        if (! ctype_digit($mobile)) {
            return [null, '手機號碼只能填數字，不要輸入國碼、+ 號。'];
        }
        if ($country === '886') {
            return preg_match('/^09\d{8}$/', $mobile)
                ? [$mobile, null]
                : [null, '台灣的手機號碼是 09 開頭的 10 碼，例如 0912345678。'];
        }
        $local = ltrim($mobile, '0');
        if (strlen($local) < 6 || strlen($local) > 15) {
            return [null, '國外的手機號碼請填 6～15 碼（不含國碼）。'];
        }

        return ['+' . $country . '-' . $local, null];
    }

    // 拆回 [國碼, 門號]，表單預填用
    public static function split(?string $phone): array
    {
        if ($phone && preg_match('/^\+(\d{1,3})-(\d+)$/', $phone, $m)) {
            return [$m[1], $m[2]];
        }

        return ['886', (string) $phone];
    }

    // 送給 TwSMS 的號碼：台灣 09xxxxxxxx；國外「國碼＋門號」（舊站 phone_to_twsms，TwSMS API 4.0）
    public static function toTwsms(string $phone): string
    {
        [$country, $local] = self::split($phone);

        return $country === '886' ? $local : $country . $local;
    }

    // 認證到期的那一刻：認證日 + 12 個月
    public static function expiresAt(?Carbon $verifiedAt): ?Carbon
    {
        return $verifiedAt?->copy()->addMonthsNoOverflow(self::VALID_MONTHS);
    }

    // 早於這個時間認證的已經過期
    public static function cutoff(): Carbon
    {
        return now()->subMonthsNoOverflow(self::VALID_MONTHS);
    }

    // 過期了就把認證拿掉（號碼留著，重新認證時預填、也還是擋別人用）。回傳有沒有拿掉
    public static function expireIfDue(User $user): bool
    {
        if ($user->phone_verified_at === null || $user->phone_verified_at->gte(self::cutoff())) {
            return false;
        }
        $user->forceFill(['phone_verified_at' => null])->save();

        return true;
    }
}
