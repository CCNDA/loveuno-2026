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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'phone_code_expires_at' => 'datetime',
            'phone_code_attempts' => 'integer',
            'password' => 'hashed',
            'is_demo' => 'boolean',
            'is_admin' => 'boolean',
            'points' => 'integer',
        ];
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
}
