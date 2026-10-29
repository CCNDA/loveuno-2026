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

    public static function map(): array
    {
        if (self::$map === null) {
            $map = [];
            foreach (['TSCharacters.txt', 'TSPhrases.txt'] as $file) {   // 詞放後面，同一個 key 以詞為準
                foreach (file(resource_path('opencc/' . $file), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    [$from, $to] = explode("\t", $line, 2);
                    $map[$from] = explode(' ', $to)[0]; // 有多個候選時取第一個
                }
            }
            self::$map = $map;
        }

        return self::$map;
    }

    public static function text(string $s): string
    {
        return strtr($s, self::map());
    }

    // 整頁 HTML：先把不能動的區塊換成記號，轉完再放回去
    public static function html(string $html): string
    {
        $keep = [];
        $protect = function (array $m) use (&$keep): string {
            $key = "\u{E000}" . count($keep) . "\u{E001}"; // 私用區字元，對照表裡不會有
            $keep[$key] = $m[0];

            return $key;
        };
        $html = preg_replace_callback('#<(textarea|script|style)\b[^>]*>.*?</\1>#is', $protect, $html);
        // 標了 data-keep-script 的（例如切換鈕上的「繁體」）不轉
        $html = preg_replace_callback('#<(a|span)\b[^>]*\bdata-keep-script\b[^>]*>.*?</\1>#is', $protect, $html);
        $html = preg_replace_callback('#\svalue\s*=\s*("[^"]*"|\'[^\']*\')#i', $protect, $html);

        $html = self::text($html);

        return strtr($html, $keep);
    }
}
