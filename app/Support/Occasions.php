<?php

namespace App\Support;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Carbon;

// 特殊節日（設計稿 PageIndex OccasionBlock）：好友的生日快到了、跟好友的好友週年。
// 「快到了」＝今天起 7 天內。生日只拿來算這個，不顯示日期（熊叔叔 10/29「3A」）。
class Occasions
{
    public const DAYS_AHEAD = 7;

    // 回傳 [['type' => 'birthday'|'anniversary', 'friend' => User, 'in_days' => int, 'years' => int（只有週年）], ...]，近的在前
    public static function for(User $me): array
    {
        $rows = Friendship::where(fn ($q) => $q->where('user_id', $me->id)->orWhere('friend_id', $me->id))->get();
        if ($rows->isEmpty()) {
            return [];
        }
        $friends = User::with('profile')->active()->whereIn('id', $rows->map->otherThan($me))
            ->whereNotIn('id', $me->blockRelatedIds())->get()->keyBy('id');
        $today = now()->startOfDay();
        $out = [];

        foreach ($rows as $f) {
            $friend = $friends->get($f->otherThan($me));
            if (! $friend) {
                continue;
            }
            $p = $friend->profile;
            if ($p && $p->birth_month && $p->birth_day) {
                $days = self::daysUntil($today, (int) $p->birth_month, (int) $p->birth_day);
                if ($days !== null && $days <= self::DAYS_AHEAD) {
                    $out[] = ['type' => 'birthday', 'friend' => $friend, 'in_days' => $days];
                }
            }
            if ($f->created_at) {
                $since = $f->created_at->copy()->startOfDay();
                $days = self::daysUntil($today, (int) $since->month, (int) $since->day);
                if ($days !== null && $days <= self::DAYS_AHEAD) {
                    $years = $today->copy()->addDays($days)->year - $since->year;
                    if ($years >= 1) {
                        $out[] = ['type' => 'anniversary', 'friend' => $friend, 'years' => $years, 'in_days' => $days];
                    }
                }
            }
        }
        usort($out, fn ($a, $b) => $a['in_days'] <=> $b['in_days']);

        return $out;
    }

    // 從今天到下一個「m 月 d 日」還有幾天（今天就是 0）。2/29 在不是閏年的年份算 2/28
    private static function daysUntil(Carbon $today, int $m, int $d): ?int
    {
        if ($m < 1 || $m > 12 || $d < 1 || $d > 31) {
            return null;
        }
        foreach ([$today->year, $today->year + 1] as $y) {
            $day = min($d, Carbon::create($y, $m, 1)->daysInMonth);
            $date = Carbon::create($y, $m, $day)->startOfDay();
            if ($date->greaterThanOrEqualTo($today)) {
                return (int) $today->diffInDays($date);
            }
        }

        return null;
    }
}
