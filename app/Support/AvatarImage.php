<?php

namespace App\Support;

use RuntimeException;

// 把上傳的照片重新做成 600×600 的 JPEG：
// - 用 GD 重新編碼，原檔裡的 EXIF（拍攝地點、相機資訊）全部丟掉，也順便擋掉藏在圖片裡的其他內容
// - 先照 EXIF 的方向轉正，再置中裁成正方形
class AvatarImage
{
    public const SIZE = 600;

    public static function fromUpload(string $path): string
    {
        $info = @getimagesize($path);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('不是支援的圖片格式');
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (! $src) {
            throw new RuntimeException('圖片讀不出來');
        }

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $src = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => imagerotate($src, 180, 0),
                6 => imagerotate($src, -90, 0),
                8 => imagerotate($src, 90, 0),
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
