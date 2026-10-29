<?php

namespace App\Models;

use Database\Factories\DiaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// user_id 不在 Fillable：作者只能由程式指定（$user->diaries()->create()），表單帶不進來
#[Fillable(['title', 'body', 'visibility', 'published_on'])]
class Diary extends Model
{
    /** @use HasFactory<DiaryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }
}
