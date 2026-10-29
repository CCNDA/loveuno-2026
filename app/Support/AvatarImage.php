<?php

namespace App\Support;

use RuntimeException;

// 把上傳的照片重新做成 600×600 的 JPEG：
// - 用 GD 重新編碼，原檔裡的 EXIF（拍攝地點、相機資訊）全部丟掉，也順便擋掉藏在圖片裡的其他內容
// - 先照 EXIF 的方向轉正，再置中裁成正方形
class AvatarImage
{
    public const SIZE = 600;

    // 解開成點陣要「寬×高×4」bytes：4000 萬像素約 160MB，再大就可能把 PHP 記憶體吃光（decompression bomb）
    public const MAX_PIXELS = 40_000_000;

    public static function fromUpload(string $path): string
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

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // PNG 透明的地方填白
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($dst, null, 85);

        return (string) ob_get_clean();
    }
}
