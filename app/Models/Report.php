<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 檢舉。reporter_id、reported_id、狀態、處理欄位都不在 Fillable：只由程式指定
#[Fillable(['reason', 'description'])]
class Report extends Model
{
    // 設計稿 PageReport 的選項，照原本順序
    public const REASONS = [
        'fraud' => '詐騙',
        'ad' => '廣告',
        'violence' => '暴力',
        'hate' => '仇恨言論',
        'other' => '其他',
    ];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reported(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
