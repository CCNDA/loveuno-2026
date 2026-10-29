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

    // 算進 9 張的（不通過的那一格只是告訴會員結果，不佔名額）
    public static function countedFor(int $userId): int
    {
        return self::where('user_id', $userId)->where('status', '!=', 'rejected')->count();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // 跟大頭照同一套：通過審核的，登入會員（沒有封鎖關係）看得到；審核中只有本人和管理員
    public function visibleTo(User $viewer): bool
    {
        if ($viewer->is_admin || $this->user_id === (int) $viewer->id) {
            return $this->status !== 'rejected' || $this->user_id === (int) $viewer->id;
        }

        return $this->status === 'approved' && ! in_array($this->user_id, $viewer->blockRelatedIds(), true)
            && $this->user->is_active; // 暫停／註銷的帳號，別人看不到相簿
    }
}
