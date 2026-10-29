<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'email', 'subject', 'body'])]
class ContactMessage extends Model
{
    // 設計稿 PageContactUs 的「聯絡事項」（交友服務相關、捐款、其他）＋帳號設定的「Bug 回報」
    public const SUBJECTS = ['service' => '交友服務相關', 'donation' => '捐款', 'bug' => 'Bug 回報', 'other' => '其他'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectLabel(): string
    {
        return self::SUBJECTS[$this->subject] ?? $this->subject;
    }
}
