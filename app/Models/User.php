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

#[Fillable(['name', 'email', 'password', 'legacy_id', 'is_demo'])]
#[Hidden(['password', 'remember_token'])]
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
            'password' => 'hashed',
            'is_demo' => 'boolean',
            'is_admin' => 'boolean',
        ];
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
            $mine = \DB::table('blocks')->where('blocker_id', $this->id)->pluck('blocked_id');
            $theirs = \DB::table('blocks')->where('blocked_id', $this->id)->pluck('blocker_id');
            $attrs->set($key, $mine->merge($theirs)->map(fn ($v) => (int) $v)->unique()->values()->all());
        }

        return $attrs->get($key);
    }

    public function hasBlocked(User $other): bool
    {
        return \DB::table('blocks')->where('blocker_id', $this->id)->where('blocked_id', $other->id)->exists();
    }

    public function blockedEitherWay(User $other): bool
    {
        return in_array((int) $other->id, $this->blockRelatedIds(), true);
    }
}
