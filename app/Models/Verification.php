<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 身份認證（職業 Email、身分證明文件、教會週報）。欄位都由程式指定，不開 Fillable。
#[Hidden(['company_email', 'email_token_hash', 'file_path'])]
class Verification extends Model
{
    public const KINDS = [
        'job' => '職業 Email 認證',
        'id' => '身分證明文件認證',
        'church' => '教會週報認證',
    ];

    // 退件理由照舊站 website/data/*_reject_reason.txt
    public const REJECT_REASONS = [
        'job' => ['公司Email與公司網址不符', '查無公司網址', '公司網頁不符合中大型企業的要求', '公司Email為公用Email Domain'],
        'id' => ['照片模糊無法辨識', '照片非可證明身分之文件', '文件中資料與會員資料不符合', '頭像與證明身分之文件不符'],
        'church' => ['照片模糊無法辨識', '照片非與教會週報之合照', '週報照片與教會不符合', '非本人之照片', '週報超過一個月以上'],
    ];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'email_sent' => '認證信已寄出，請到公司（學校）信箱點信裡的連結',
            'pending' => '審核中（約 3-5 個工作天）',
            'approved' => '已通過',
            'rejected' => '沒有通過：' . ($this->reject_reason !== '' ? $this->reject_reason : '請重新申請'),
            default => $this->status,
        };
    }
}
