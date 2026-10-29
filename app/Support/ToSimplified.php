<?php

namespace App\Support;

// 繁體轉簡體（顯示用）。對照表是 OpenCC 1.1.9 的 TSPhrases.txt＋TSCharacters.txt（Apache-2.0，
// 放在 resources/opencc/，附 LICENSE）。先比詞、再比字：strtr 遇到多組對照時會挑最長的先換。
//
// 🔴 只轉「看的」文字，不轉表單裡的值：<textarea> 的內容、value="…"、<script>、<style> 原封不動。
//    不然在簡體模式打開「編輯個人檔案」，存檔就會把資料庫裡的繁體改成簡體。
class ToSimplified
{
    private static ?array $map = null;

    // 對照表編譯成 bootstrap/cache/opencc-t2s.php（var_export 的陣列），之後每個請求直接 require，
    // 有 opcache 就幾乎不花時間；原始的 txt 比較新就重編一次。
    public static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }
        $sources = [resource_path('opencc/TSCharacters.txt'), resource_path('opencc/TSPhrases.txt')]; // 詞放後面，同一個 key 以詞為準
        $compiled = base_path('bootstrap/cache/opencc-t2s.php');
        $newest = max(array_map('filemtime', $sources));
        if (! is_file($compiled) || filemtime($compiled) < $newest) {
            $map = [];
            foreach ($sources as $file) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    [$from, $to] = explode("\t", $line, 2);
                    $map[$from] = explode(' ', $to)[0]; // 有多個候選時取第一個
                }
            }
            $tmp = $compiled . '.' . getmypid() . '.tmp';
            file_put_contents($tmp, '<?php return ' . var_export($map, true) . ';' . PHP_EOL);
            rename($tmp, $compiled); // 換檔是原子的，其他請求不會讀到寫一半的檔
            self::$map = $map;

            return $map;
        }

        return self::$map = require $compiled;
    }

    public static function text(string $s): string
    {
        return strtr($s, self::map());
    }

    // 整頁 HTML：先把不能動的區塊換成記號，轉完再放回去
    public static function html(string $html): string
    {
        $original = $html;
        $keep = [];
        // 記號＝私用區字元＋這一次隨機的字串：會員就算在內容裡打出私用區字元，也猜不到這次的記號
        $nonce = bin2hex(random_bytes(8));
        $protect = function (array $m) use (&$keep, $nonce): string {
            $key = "\u{E000}" . $nonce . '-' . count($keep) . "\u{E001}";
            $keep[$key] = $m[0];

            return $key;
        };
        foreach ([
            '#<(textarea|script|style)\b[^>]*>.*?</\1>#is',
            // 標了 data-keep-script 的（例如切換鈕上的「繁體」）不轉
            '#<(a|span)\b[^>]*\bdata-keep-script\b[^>]*>.*?</\1>#is',
            // value="…"、value='…'、value=沒引號
            '#\svalue\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i',
        ] as $re) {
            $html = preg_replace_callback($re, $protect, $html);
            if ($html === null) {
                return $original; // 正規式失敗（例如頁面太大）：寧可不轉，也不要 500
            }
        }

        $html = self::text($html);

        return strtr($html, $keep);
    }
}
