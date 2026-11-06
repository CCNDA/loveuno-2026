<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

// 禮物、約會清單的「勾選刪除」（熊叔叔 11/6 決定 #9，跟 #8 傳紙條同一套）：只把我這邊標成刪掉，對方那邊還在。
// 表要有 sender_id、recipient_id、sender_deleted_at、recipient_deleted_at。
trait HiddenPerSide
{
    // 我看得到的：我寄的（我沒刪）＋寄給我的（我沒刪）
    public function scopeVisibleTo(Builder $q, User $me): Builder
    {
        return $q->where(function ($w) use ($me) {
            $w->where(fn ($s) => $s->where('sender_id', $me->id)->whereNull('sender_deleted_at'))
                ->orWhere(fn ($r) => $r->where('recipient_id', $me->id)->whereNull('recipient_deleted_at'));
        });
    }

    // 只動自己寄的或寄給自己的；回傳刪了幾筆
    public static function hideFor(User $me, array $ids): int
    {
        $ids = collect($ids)->map(fn ($v) => (int) $v)->filter()->unique()->take(200)->all();
        if (! $ids) {
            return 0;
        }

        return static::whereIn('id', $ids)->where('sender_id', $me->id)->whereNull('sender_deleted_at')->update(['sender_deleted_at' => now()])
            + static::whereIn('id', $ids)->where('recipient_id', $me->id)->whereNull('recipient_deleted_at')->update(['recipient_deleted_at' => now()]);
    }
}
