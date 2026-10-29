<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 日記留言。diary_id、user_id 不在 Fillable：只由程式指定
#[Fillable(['body'])]
class DiaryComment extends Model
{
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'diary_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diary(): BelongsTo
    {
        return $this->belongsTo(Diary::class);
    }

    // 留言者本人、日記作者、管理員可以刪
    public function deletableBy(User $user): bool
    {
        return $user->is_admin || $this->user_id === (int) $user->id || $this->diary->isOwnedBy($user);
    }

    // 設計稿的寫法：「1天前」「20小時前」。App 語系是 en，Carbon 的 diffForHumans 會出英文，所以自己寫
    public function ago(): string
    {
        $s = max(0, now()->getTimestamp() - $this->created_at->getTimestamp());

        return match (true) {
            $s < 60 => '剛剛',
            $s < 3600 => intdiv($s, 60) . '分鐘前',
            $s < 86400 => intdiv($s, 3600) . '小時前',
            $s < 86400 * 30 => intdiv($s, 86400) . '天前',
            default => $this->created_at->format('Y-m-d'),
        };
    }
}
