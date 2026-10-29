<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 約會（約見面邀請）。欄位都由 MeetupController 指定，不開 Fillable。
// 狀態照舊站 private_date.result：0 等回覆 → 1 準備參加 / -1 婉拒；1 → -2 取消；約會日過後 → 3 已參加 / 沒參加。
// 舊站「沒參加」跟「取消」都存 -2、過期要有人打開才改，新版分開存（absent），過期用日期算（state()），不用排程。
class Meetup extends Model
{
    // 舊站 private_date.php 的活動選項（設計稿只有「主日崇拜」一個，見 docs #46）
    public const ACTIVITIES = ['sunday' => '主日聚會', 'group' => '小組聚會', 'evangelism' => '佈道會', 'special' => '特別聚會', 'agape' => '愛宴'];

    // 舊站「歡迎邀請家人朋友一起／希望你單獨參加」
    public const COMPANY = ['friends' => '歡迎邀請家人朋友一起', 'alone' => '希望你單獨參加'];

    public const STATES = [
        'pending' => '等待回覆', 'accepted' => '準備參加', 'declined' => '婉拒了', 'cancelled' => '取消了',
        'attended' => '已參加', 'absent' => '沒有參加', 'expired' => '超過約會時間，沒有回覆', 'past' => '約會日過了，等待確認有沒有參加',
    ];

    protected function casts(): array
    {
        return ['meet_on' => 'date', 'responded_at' => 'datetime', 'closed_at' => 'datetime', 'sender_id' => 'integer', 'recipient_id' => 'integer'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    // 約會那一天開始（當天）就算「過了」，跟舊站 check_if_expire 一樣
    public function dayCame(): bool
    {
        return $this->meet_on->lte(today());
    }

    // 畫面上的狀態：資料庫的 status，再加上用日期算的 expired、past
    public function state(): string
    {
        if ($this->status === 'pending' && $this->dayCame()) {
            return 'expired';
        }
        if ($this->status === 'accepted' && $this->dayCame()) {
            return 'past';
        }

        return $this->status;
    }

    public function stateLabel(): string
    {
        return self::STATES[$this->state()];
    }

    public function involves(User $u): bool
    {
        return in_array((int) $u->id, [$this->sender_id, $this->recipient_id], true);
    }

    public function otherThan(User $me): ?User
    {
        return $this->sender_id === (int) $me->id ? $this->recipient : $this->sender;
    }
}
