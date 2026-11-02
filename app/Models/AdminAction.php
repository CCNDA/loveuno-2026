<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 後台對會員做過的事（只新增、不修改）
class AdminAction extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'suspend' => '停權',
        'unsuspend' => '解除停權',
        'marital' => '改婚姻狀況',
        'points' => '加減積點',
        'rename' => '改暱稱',
        'role' => '改管理員權限',
    ];

    public const ROLE_CHANGES = [
        'admin_on' => '設為管理員',
        'admin_off' => '取消管理員',
        'super_on' => '設為最高管理員',
        'super_off' => '取消最高管理員',
    ];

    protected function casts(): array
    {
        return ['detail' => 'array', 'created_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function record(User $admin, User $user, string $action, array $detail): self
    {
        $a = new self;
        $a->forceFill(['admin_id' => $admin->id, 'user_id' => $user->id, 'action' => $action, 'detail' => $detail])->save();

        return $a;
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
