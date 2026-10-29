<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 「為你禱告」：誰為哪一篇日記按了（一人一篇一次）。只有 DiaryInteractionController 會建，沒有表單直接寫進來
#[Fillable(['diary_id', 'user_id'])]
class DiaryPrayer extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
