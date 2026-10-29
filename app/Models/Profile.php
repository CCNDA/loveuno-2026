<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'gender', 'birth_year', 'live_place', 'church', 'church_visible', 'job', 'intro', 'hope',
    'pref_gender', 'pref_age_min', 'pref_age_max', 'pref_place'])]
class Profile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'church_visible' => 'boolean',
            'birth_year' => 'integer',
            'photo_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // 大頭照誰看得到：通過審核的，登入會員都看得到；審核中的只有本人和管理員
    public function photoVisibleTo(?User $viewer): bool
    {
        if (! $this->photo_path || ! $viewer) {
            return false;
        }

        return $this->photo_status === 'approved'
            || ($this->photo_status === 'pending' && ($viewer->id === $this->user_id || $viewer->is_admin));
    }

    // 畫面上用教會習慣的稱呼
    public function genderLabel(): string
    {
        return ['男' => '弟兄', '女' => '姊妹'][$this->gender] ?? '';
    }

    // 只顯示年齡層，不顯示生日（舊站直接秀出生日與真實姓名，新版不這樣做）
    public function ageGroup(): ?string
    {
        if (! $this->birth_year) {
            return null;
        }
        $age = (int) now()->format('Y') - $this->birth_year;

        return intdiv($age, 5) * 5 . '～' . (intdiv($age, 5) * 5 + 4) . ' 歲';
    }
}
