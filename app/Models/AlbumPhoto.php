<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 相簿照片。欄位都由 AlbumController 指定，不開 Fillable
class AlbumPhoto extends Model
{
    public const MAX = 9; // 設計稿「相簿照片(上限為9張)」

    protected function casts(): array
    {
        return ['user_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // 跟大頭照同一套：通過審核的，登入會員（沒有封鎖關係）看得到；審核中只有本人和管理員
    public function visibleTo(User $viewer): bool
    {
        if ($viewer->is_admin || $this->user_id === (int) $viewer->id) {
            return true;
        }

        return $this->status === 'approved' && ! in_array($this->user_id, $viewer->blockRelatedIds(), true);
    }
}
