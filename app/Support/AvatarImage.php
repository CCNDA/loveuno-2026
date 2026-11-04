<?php

namespace App\Support;

use RuntimeException;

// 把上傳的照片重新做成 JPEG（大頭照 600×600、封面 1200×400 裁切；日記附圖 fit() 只縮不裁）：
// - 用 GD 重新編碼，原檔裡的 EXIF（拍攝地點、相機資訊）全部丟掉，也順便擋掉藏在圖片裡的其他內容
// - 先照 EXIF 的方向轉正；大頭照、封面再照目標比例置中裁切
class AvatarImage
{
    public const SIZE = 600;

    // 解開成點陣要「寬×高×4」bytes：4000 萬像素約 160MB，再大就可能把 PHP 記憶體吃光（decompression bomb）
    public const MAX_PIXELS = 40_000_000;

    // $w×$h：大頭照 600×600、封面 1200×400；照目標比例置中裁切再縮放
    public static function fromUpload(string $path, int $w = self::SIZE, int $h = self::SIZE): string
    {
        $src = self::load($path);

        $sw = imagesx($src);
        $sh = imagesy($src);
        // 從原圖裡挑出跟目標同比例、最大的置中區塊
        $cw = $sw;
        $ch = (int) round($sw * $h / $w);
        if ($ch > $sh) {
            $ch = $sh;
            $cw = (int) round($sh * $w / $h);
        }
        $dst = imagecreatetruecolor($w, $h);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // PNG 透明的地方填白
        imagecopyresampled($dst, $src, 0, 0, intdiv($sw - $cw, 2), intdiv($sh - $ch, 2), $w, $h, $cw, $ch);

        return self::jpeg($dst);
    }

    // 日記附圖：不裁切，長邊超過 $max 才等比例縮小（設計稿日記卡片的大圖）
    public static function fit(string $path, int $max = 1200): string
    {
        $src = self::load($path);
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min(1, $max / max($sw, $sh));
        $w = max(1, (int) round($sw * $scale));
        $h = max(1, (int) round($sh * $scale));
        $dst = imagecreatetruecolor($w, $h);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // PNG 透明的地方填白
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);

        return self::jpeg($dst);
    }

    // 檢查格式與像素數、讀成 GD 圖、照 EXIF 方向轉正
    private static function load(string $path): \GdImage
    {
        $info = @getimagesize($path);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('不是支援的圖片格式');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new RuntimeException('圖片像素太多');
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (! $src) {
            throw new RuntimeException('圖片讀不出來');
        }

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            // EXIF 方向 1～8：2、4、5、7 是鏡像，要先翻再轉
            $o = (int) ($exif['Orientation'] ?? 1);
            if (in_array($o, [2, 4, 5, 7], true)) {
                imageflip($src, IMG_FLIP_HORIZONTAL);
            }
            $src = match ($o) {
                3, 4 => imagerotate($src, 180, 0),
                5, 6 => imagerotate($src, -90, 0),
                7, 8 => imagerotate($src, 90, 0),
                default => $src,
            };
        }

        return $src;
    }

    private static function jpeg(\GdImage $img): string
    {
        ob_start();
        imagejpeg($img, null, 85);

        return (string) ob_get_clean();
    }
}
