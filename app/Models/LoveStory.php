<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 愛的見證。欄位都由 LoveStoryController 指定，不開 Fillable
class LoveStory extends Model
{
    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'submitter_id' => 'integer'];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    public function male(): BelongsTo
    {
        return $this->belongsTo(User::class, 'male_id');
    }

    public function female(): BelongsTo
    {
        return $this->belongsTo(User::class, 'female_id');
    }

    // 通過的大家都看得到（不用登入）；審核中、不通過的只有投稿的人和管理員
    public function visibleTo(?User $viewer): bool
    {
        return $this->status === 'approved' || ($viewer && ($viewer->is_admin || $viewer->id === $this->submitter_id));
    }

    public function excerpt(int $len = 90): string
    {
        $t = preg_replace('/\s+/u', ' ', trim($this->body));

        return mb_strlen($t) > $len ? mb_substr($t, 0, $len) . '…' : $t;
    }
}
