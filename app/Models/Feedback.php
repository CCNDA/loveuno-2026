<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 會員按「回報問題／建議」送來的意見（後台 /admin/feedback 處理）
class Feedback extends Model
{
    protected $table = 'feedback';

    public const KINDS = ['problem' => '遇到問題', 'idea' => '建議', 'other' => '其他'];

    public const STATUSES = ['open' => '還沒處理', 'doing' => '處理中', 'done' => '已處理'];

    protected $fillable = ['kind', 'body'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    // 從 User-Agent 粗略看是手機還是電腦、什麼瀏覽器（給管理員看，不用很準）
    public function deviceLabel(): string
    {
        $ua = $this->user_agent;
        $device = preg_match('/iPhone|Android.*Mobile|Mobile/i', $ua) ? '手機' : (preg_match('/iPad|Tablet|Android/i', $ua) ? '平板' : '電腦');
        $browser = match (true) {
            str_contains($ua, 'Line/') => 'LINE 內建瀏覽器',
            str_contains($ua, 'FBAN') || str_contains($ua, 'FBAV') => 'Facebook 內建瀏覽器',
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => '其他瀏覽器',
        };

        return $device . '・' . $browser . ($this->screen !== '' ? '・' . $this->screen : '');
    }
}
