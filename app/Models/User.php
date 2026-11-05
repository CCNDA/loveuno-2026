<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password', 'legacy_id', 'is_demo'])]
#[Hidden(['password', 'remember_token', 'phone', 'phone_pending', 'phone_code_hash'])]
// MustVerifyEmail：新註冊的會員要先驗證 email 才能看會員頁（verified middleware）
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // 跟資料庫的預設值一樣：剛 create、還沒重新讀過的物件也有這些值（不然 is_active 會是 null，被當成暫停）
    protected $attributes = [
        'is_active' => true,
        'notify_email' => true,
        'font_size' => 'medium',
        'font_family' => 'system',
        'script' => 'hant',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_bounced_at' => 'datetime', // 退信（信箱寄不到）：有值就停寄通知，登入後請他重新驗證或改信箱
            'last_login_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'legacy_status' => 'integer',
            'phone_code_expires_at' => 'datetime',
            'phone_code_attempts' => 'integer',
            'password' => 'hashed',
            'is_demo' => 'boolean',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean', // 最高管理員：可以在後台設定誰是管理員
            'points' => 'integer',
            'notify_email' => 'boolean',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
            'suspended_at' => 'datetime', // 停權：舊站搬來的（2026_10_30_000029）或後台停權（2026_11_02_000001），哪個先跑就由哪個建欄位
            'suspend_prev_active' => 'boolean',
        ];
    }

    // 別人看得到的會員：沒暫停、沒註銷（註銷的 is_active 也是 false）。暫停／註銷要擋的地方都用這個，免得漏掉
    // 管理員權限的說法（後台列表、會員頁、user:admin 指令共用）
    public function roleLabel(): string
    {
        return $this->is_super_admin ? '最高管理員' : ($this->is_admin ? '管理員' : '一般會員');
    }

    // 退信：信箱寄不到（硬退）。把 email 改回「未驗證」，登入後就會被帶到驗證頁（verified middleware）；
    // 有值的時候除了驗證信以外的信都不寄（routeNotificationForMail）。重新驗證或改信箱後清掉。
    public function markEmailBounced(string $reason): void
    {
        $this->forceFill([
            'email_bounced_at' => now(),
            'email_bounce_reason' => mb_substr(trim($reason), 0, 200),
            'email_verified_at' => null,
        ])->save();
    }

    public function clearEmailBounce(): void
    {
        if ($this->email_bounced_at) {
            $this->forceFill(['email_bounced_at' => null, 'email_bounce_reason' => ''])->save();
        }
    }

    // 退信中只寄驗證信（讓他能重新驗證），其他通知（禮物、紙條、審核結果…）一律不寄：寄了也是退，還會傷到寄件信譽
    public function routeNotificationForMail($notification = null): ?string
    {
        if ($this->email_bounced_at && ! $notification instanceof \Illuminate\Auth\Notifications\VerifyEmail) {
            return null;
        }

        return $this->email;
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->where('is_active', true);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }

    // 通過的身份認證（職業、身分證明、教會週報）：[kind => Verification]
    public function approvedVerifications(): \Illuminate\Support\Collection
    {
        return $this->verifications->where('status', 'approved')->keyBy('kind');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function diaries(): HasMany
    {
        return $this->hasMany(Diary::class);
    }

    // 跟我有封鎖關係的人（我封鎖的＋封鎖我的）。
    // 快取放在「這一個請求」上（每個頭像都會問一次，不快取會變成很多次查詢）；
    // 不放在 model 物件上，因為同一個物件跨請求（例如測試）時會拿到舊的名單。
    public function blockRelatedIds(): array
    {
        $key = 'block_ids:' . $this->id;
        $attrs = request()->attributes;
        if (! $attrs->has($key)) {
            $mine = DB::table('blocks')->where('blocker_id', $this->id)->pluck('blocked_id')->map(fn ($v) => (int) $v)->all();
            $theirs = DB::table('blocks')->where('blocked_id', $this->id)->pluck('blocker_id')->map(fn ($v) => (int) $v)->all();
            $attrs->set($key, ['mine' => $mine, 'theirs' => $theirs]);
        }
        $v = $attrs->get($key);

        return array_values(array_unique(array_merge($v['mine'], $v['theirs'])));
    }

    // 封鎖我的人（我看不到他們；他們封鎖前寄給我的紙條，我也打不開）
    public function blockedByIds(): array
    {
        $this->blockRelatedIds();

        return request()->attributes->get('block_ids:' . $this->id)['theirs'];
    }

    public function hasBlocked(User $other): bool
    {
        $this->blockRelatedIds(); // 確保快取有值

        return in_array((int) $other->id, request()->attributes->get('block_ids:' . $this->id)['mine'], true);
    }

    public function blockedEitherWay(User $other): bool
    {
        return in_array((int) $other->id, $this->blockRelatedIds(), true);
    }

    // 我的好友等級：[對方 id => 1～3]。跟封鎖名單一樣快取在「這一個請求」上（會員列表每張卡片都會問）
    public function friendLevels(): array
    {
        $key = 'friend_levels:' . $this->id;
        $attrs = request()->attributes;
        if (! $attrs->has($key)) {
            $map = [];
            foreach (Friendship::where('user_id', $this->id)->orWhere('friend_id', $this->id)->get() as $f) {
                $map[$f->otherThan($this)] = $f->level;
            }
            $attrs->set($key, $map);
        }

        return $attrs->get($key);
    }

    // 0＝路人甲（Friendship::LEVELS）
    public function friendLevelWith(User $other): int
    {
        return $this->friendLevels()[(int) $other->id] ?? 0;
    }

    public static function forgetFriendLevels(User ...$users): void
    {
        foreach ($users as $u) {
            request()->attributes->remove('friend_levels:' . $u->id);
        }
    }

    // 手機認證還在 12 個月內（熊叔叔 10/29「2b照舊站」）
    public function hasValidPhone(): bool
    {
        return $this->phone_verified_at !== null && $this->phone_verified_at->gte(\App\Support\Phone::cutoff());
    }

    // 列給別人看的會員：手機認證還在 12 個月內
    public function scopePhoneVerified(\Illuminate\Database\Eloquent\Builder $q): void
    {
        $q->whereNotNull('phone_verified_at')->where('phone_verified_at', '>=', \App\Support\Phone::cutoff());
    }
}
