<?php

namespace App\Support;

use Illuminate\Support\Carbon;

// 「最近登入」「最近更新」只寫到「幾天前」，不寫幾點幾分（設計稿 PagePersonal：「小時前」；寫到分鐘就看得出作息）
class Ago
{
    public static function days(Carbon $time): string
    {
        $days = (int) $time->copy()->startOfDay()->diffInDays(now()->startOfDay());

        return match (true) {
            $days <= 0 => '今天',
            $days === 1 => '昨天',
            $days < 30 => $days . ' 天前',
            $days < 365 => intdiv($days, 30) . ' 個月前',
            default => '一年以上',
        };
    }
}
