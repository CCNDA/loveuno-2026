<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

// 會員照片的讀／刪。兩種路徑：
// - 一般路徑：新版自己存的檔（avatars 磁碟）
// - legacy:<目錄>/<檔名>：舊站已經放在 R2 公開桶的原檔，新版直接用、不搬（熊叔叔 11/22 TG 選甲「直接用 R2 原檔」：
//   「之前就是為了新版才把照片都放去R2」）。只用公開網址讀，新版手上沒有那個桶的寫入權限；
//   公測期間舊站也還在用同一個檔，所以新版刪照片只刪紀錄、不刪檔。
final class PhotoStore
{
    public const LEGACY_PREFIX = 'legacy:';

    // 舊站照片可能在的目錄（get_loveuno_image_url 的 $type）
    private const LEGACY_DIRS = ['member_photo/image', 'diary_pic', 'member_photo/testi_pic'];

    private const MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public static function isLegacy(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, self::LEGACY_PREFIX);
    }

    // 匯入用：組出 legacy: 路徑；目錄不在清單、檔名怪怪的就回 null
    public static function legacyRef(string $dir, string $file): ?string
    {
        $file = ltrim($file, '/');
        if (! in_array($dir, self::LEGACY_DIRS, true) || $file === '' || str_contains($file, '..') || ! preg_match('#^[^\x00-\x1f?\#\\\\]+$#u', $file)) {
            return null;
        }

        return self::LEGACY_PREFIX . $dir . '/' . $file;
    }

    // 讀出照片內容與 Content-Type；沒有、讀不到、不是圖片都回 null（呼叫端回 404）
    public static function read(?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (! self::isLegacy($path)) {
            $disk = Storage::disk('avatars');

            return $disk->exists($path) ? ['bytes' => $disk->get($path), 'type' => 'image/jpeg'] : null;
        }
        $url = self::legacyUrl($path);
        if ($url === null) {
            return null;
        }
        try {
            $res = Http::timeout(10)->connectTimeout(5)->withoutRedirecting()->get($url);
        } catch (Throwable) {
            return null;
        }
        if (! $res->successful()) {
            return null;
        }
        $bytes = $res->body();
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info['mime'] ?? '', self::MIMES, true)) {
            return null;
        }

        return ['bytes' => $bytes, 'type' => $info['mime']];
    }

    // 刪檔：舊站的原檔不刪（舊站還在用），只刪新版自己存的
    public static function delete(?string $path): void
    {
        if ($path !== null && $path !== '' && ! self::isLegacy($path)) {
            Storage::disk('avatars')->delete($path);
        }
    }

    private static function legacyUrl(string $path): ?string
    {
        $base = rtrim((string) config('loveuno.legacy_photo_url'), '/');
        $rest = substr($path, strlen(self::LEGACY_PREFIX));
        foreach (self::LEGACY_DIRS as $dir) {
            if (str_starts_with($rest, $dir . '/')) {
                $ref = self::legacyRef($dir, substr($rest, strlen($dir) + 1));
                if ($base === '' || $ref === null || ! str_starts_with($base, 'https://')) {
                    return null;
                }

                return $base . '/' . $dir . '/' . str_replace('%2F', '/', rawurlencode(substr($rest, strlen($dir) + 1)));
            }
        }

        return null;
    }
}
