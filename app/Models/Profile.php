<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'gender', 'birth_year', 'live_place', 'church', 'church_visible', 'job', 'intro', 'hope'])]
class Profile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'church_visible' => 'boolean',
            'birth_year' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
