<?php

namespace App\Support;

// 舊站存的文字常夾 HTML（<br>、&nbsp;）：轉成純文字（<br> → 換行、其他標籤拿掉、實體字解碼）
// 熊叔叔 11/6 TG：自我介紹顯示出「<br><br>」
class LegacyText
{
    public static function plain(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = preg_replace('/<br\s*\/?>/i', "\n", $s);
        $s = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $s);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace("\u{00A0}", ' ', $s);
        $s = preg_replace("/\r\n?/", "\n", $s);

        return trim(preg_replace("/\n{3,}/", "\n\n", $s));
    }
}
