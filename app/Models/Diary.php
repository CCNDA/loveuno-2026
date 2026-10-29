<?php

namespace App\Models;

use Database\Factories\DiaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// user_id 不在 Fillable：作者只能由程式指定（$user->diaries()->create()），表單帶不進來。
// image_* 也不在 Fillable：只有上傳流程（DiaryWriteController）用 forceFill 設定。
#[Fillable(['title', 'body', 'visibility', 'is_prayer', 'published_on'])]
class Diary extends Model
{
    /** @use HasFactory<DiaryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'user_id' => 'integer',
            'is_prayer' => 'boolean',
            'image_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prayers(): HasMany
    {
        return $this->hasMany(DiaryPrayer::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DiaryComment::class);
    }

    // 列表用：禱告人數、留言數、我有沒有按過「為你禱告」。
    // 留言數不算跟我有封鎖關係的人（內頁也不顯示），兩邊數字要一樣
    public function scopeWithInteractions(Builder $query, User $viewer): Builder
    {
        return $query->withCount(self::interactionCounts($viewer))
            ->withExists(self::prayedByMe($viewer));
    }

    // 內頁用：已經拿到這篇了，補上同樣的三個數
    public function loadInteractions(User $viewer): static
    {
        return $this->loadCount(self::interactionCounts($viewer))->loadExists(self::prayedByMe($viewer));
    }

    private static function interactionCounts(User $viewer): array
    {
        return ['prayers', 'comments' => fn (Builder $q) => $q->whereNotIn('user_id', $viewer->blockRelatedIds())];
    }

    private static function prayedByMe(User $viewer): array
    {
        return ['prayers as prayed_by_me' => fn (Builder $q) => $q->where('user_id', $viewer->id)];
    }

    // 看得到的日記：公開的，加上自己的（含不公開）
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $query->where(function (Builder $q) use ($viewer) {
            $q->where('visibility', 'public')->orWhere('user_id', $viewer->id);
        });
    }

    public function isVisibleTo(User $viewer): bool
    {
        return $this->visibility === 'public' || $this->isOwnedBy($viewer);
    }

    // 看得到、也沒有封鎖關係：才能看內容、禱告、留言
    public function isReachableBy(User $viewer): bool
    {
        return $this->isVisibleTo($viewer) && ! $viewer->blockedEitherWay($this->user)
            && ($this->user->is_active || $this->isOwnedBy($viewer)); // 暫停的帳號的日記別人看不到
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    // 附圖不用審核（熊叔叔 10/29「1B」）：看得到這篇日記的人就看得到照片
    public function imageVisibleTo(User $viewer): bool
    {
        return (bool) $this->image_path && $this->isReachableBy($viewer);
    }
}
