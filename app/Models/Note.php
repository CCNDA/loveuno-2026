<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 傳紙條。寄件人／收件人只能由程式指定，不在 Fillable。
#[Fillable(['body'])]
class Note extends Model
{
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
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

    public static function unreadCountFor(User $user): int
    {
        return static::where('recipient_id', $user->id)->whereNull('read_at')->count();
    }
}
