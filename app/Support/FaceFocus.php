<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

// 找照片裡的臉（OpenCV YuNet，/opt/loveuno-face/face_focus.py），回 [x, y]（0～100）；找不到、沒裝、出錯都回 null
// 熊叔叔 2026-11-05「愛的見證照片是否可以判斷臉，而不是被切掉」→「好安裝」
class FaceFocus
{
    public static function find(string $file): ?array
    {
        $py = (string) config('loveuno.face.python');
        $script = (string) config('loveuno.face.script');
        if (! is_executable($py) || ! is_readable($script) || ! is_readable($file)) {
            return null;
        }
        $p = new Process([$py, $script, $file]);
        $p->setTimeout(15);
        try {
            $p->run();
        } catch (\Throwable $e) {
            Log::warning('找臉逾時或失敗：' . $e->getMessage());

            return null;
        }
        if (! $p->isSuccessful()) {
            return null;
        }
        $j = json_decode(trim($p->getOutput()), true);
        if (! is_array($j) || ! is_numeric($j['x'] ?? null) || ! is_numeric($j['y'] ?? null)) { // 小鹿 11/6：先確認是數字再收
            return null;
        }

        return [max(0, min(100, (int) $j['x'])), max(0, min(100, (int) $j['y']))];
    }
}
