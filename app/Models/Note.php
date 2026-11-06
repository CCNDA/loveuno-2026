<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 傳紙條。寄件人／收件人只能由程式指定，不在 Fillable。
#[Fillable(['body', 'title'])]
class Note extends Model
{
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'sender_deleted_at' => 'datetime',
            'recipient_deleted_at' => 'datetime',
            'sender_id' => 'integer',
            'recipient_id' => 'integer',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    // 兩個人之間的所有紙條（不管誰寄給誰）
    public function scopeBetween(Builder $q, User $a, User $b): Builder
    {
        // 整組包在一個括號裡，後面再接其他條件才不會被 OR 吃掉
        return $q->where(function ($outer) use ($a, $b) {
            $outer->where(function ($w) use ($a, $b) {
                $w->where('sender_id', $a->id)->where('recipient_id', $b->id);
            })->orWhere(function ($w) use ($a, $b) {
                $w->where('sender_id', $b->id)->where('recipient_id', $a->id);
            });
        });
    }

    // 我看得到的：我寄的（我沒刪）＋寄給我的（我沒刪）。熊叔叔 11/6：清單可以勾選刪除，只刪自己這邊
    public function scopeVisibleTo(Builder $q, User $me): Builder
    {
        return $q->where(function ($w) use ($me) {
            $w->where(fn ($s) => $s->where('sender_id', $me->id)->whereNull('sender_deleted_at'))
                ->orWhere(fn ($r) => $r->where('recipient_id', $me->id)->whereNull('recipient_deleted_at'));
        });
    }

    // 顯示用的標題：有寫就用；舊紙條沒有標題，用內文前 20 字
    public function displayTitle(): string
    {
        $t = trim((string) $this->title);

        // 不用 Str::limit：它算顯示寬度，中文一字算 2，只會剩 10 字
        $b = trim(preg_replace('/\s+/u', ' ', (string) $this->body));

        return $t !== '' ? $t : (mb_strlen($b) > 20 ? mb_substr($b, 0, 20) . '…' : $b);
    }

    public static function unreadCountFor(User $user): int
    {
        return static::where('recipient_id', $user->id)->whereNull('read_at')->whereNull('recipient_deleted_at')
            ->whereNotIn('sender_id', $user->blockedByIds())
            ->count();
    }
}
