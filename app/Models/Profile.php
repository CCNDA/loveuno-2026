<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'gender', 'birth_year', 'birth_month', 'birth_day', 'live_place', 'church', 'church_visible', 'job', 'intro', 'hope',
    'pref_gender', 'pref_age_min', 'pref_age_max', 'pref_place',
    'real_name', 'home_phone', 'faith', 'marital', 'children', 'education', 'blood_type', 'height', 'weight', 'smoking', 'drinking',
    'family_planning', 'headline', 'verse_book', 'verse_chapter', 'verse_verse', 'verse_text', 'languages', 'hobbies', 'sports', 'love_history'])]
// 轉成陣列／JSON 時不輸出：生日月日（只拿來算「生日快到了」，10/29「3A」）、真實姓名與住家電話（只有管理員看得到，10/29「1B」）
#[Hidden(['birth_month', 'birth_day', 'real_name', 'home_phone'])]
class Profile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'preferences_saved_at' => 'datetime',
            'church_visible' => 'boolean',
            'birth_year' => 'integer',
            'height' => 'integer',
            'weight' => 'integer',
            'verse_chapter' => 'integer',
            'verse_verse' => 'integer',
            'languages' => 'array',
            'hobbies' => 'array',
            'sports' => 'array',
            'love_history' => 'array',
            'photo_updated_at' => 'datetime',
            'cover_updated_at' => 'datetime',
        ];
    }

    // 會員檔案「個人資料」分頁要列的欄位（空的不列）
    public function detailRows(): array
    {

        return array_filter([
            '性別' => $this->gender !== '' ? $this->genderLabel() : null,
            '年齡' => $this->ageGroup(),
            '居住地' => $this->live_place,
            '信仰' => $this->faith,
            '婚姻狀況' => $this->marital,
            '子女人數' => $this->children,
            '教育程度' => $this->education,
            '職業' => $this->job,
            '是否喝酒' => $this->drinking,
            '是否抽菸' => $this->smoking,
            '血型' => $this->blood_type,
            '計劃生育' => $this->family_planning,
            '身高' => $this->height ? $this->height . ' cm' : null,
            '體重' => $this->weight ? $this->weight . ' kg' : null,
            '教會' => $this->church_visible ? $this->church : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    // 「自我介紹」分頁有沒有東西可以看
    public function hasIntroduction(): bool
    {
        return ($this->headline ?? '') !== '' || $this->intro || ($this->verse_book ?? '') !== ''
            || ! empty($this->hobbies) || ! empty($this->sports) || ! empty($this->languages) || $this->hope;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // 大頭照誰看得到：通過審核的，登入會員都看得到；審核中的只有本人和管理員
    public function photoVisibleTo(?User $viewer): bool
    {
        return $this->imageVisibleTo('photo', $viewer);
    }

    public function coverVisibleTo(?User $viewer): bool
    {
        return $this->imageVisibleTo('cover', $viewer);
    }

    // $kind：photo（大頭照）或 cover（個人化封面）
    public function imageVisibleTo(string $kind, ?User $viewer): bool
    {
        $path = $this->{$kind . '_path'};
        $status = $this->{$kind . '_status'};
        if (! $path || ! $viewer) {
            return false;
        }
        if (! $viewer->is_admin && $viewer->id !== $this->user_id && in_array((int) $this->user_id, $viewer->blockRelatedIds(), true)) {
            return false; // 封鎖（任一方）就看不到照片；管理員審核不受影響
        }
        if (! $viewer->is_admin && $viewer->id !== $this->user_id && ! $this->user->is_active) {
            return false; // 暫停／註銷的帳號，別人看不到照片
        }

        return $status === 'approved'
            || ($status === 'pending' && ($viewer->id === $this->user_id || $viewer->is_admin));
    }

    // 畫面上用教會習慣的稱呼
    public function genderLabel(): string
    {
        return ['男' => '弟兄', '女' => '姊妹'][$this->gender] ?? '';
    }

    // 用出生年篩年齡（跟 ageGroup() 同一種算法）：認識朋友的條件搜尋、日記牆的好友推薦共用
    public function scopeAgeBetween(\Illuminate\Database\Eloquent\Builder $q, ?int $min, ?int $max): \Illuminate\Database\Eloquent\Builder
    {
        $year = (int) now()->format('Y');

        return $q->when($min, fn ($w) => $w->where('birth_year', '<=', $year - $min))
            ->when($max, fn ($w) => $w->where('birth_year', '>=', $year - $max));
    }

    // 交友條件 → 篩選用的值（認識朋友列表、首頁好友推薦、歡迎新朋友共用）。
    // 年齡放寬到 5 歲一格（跟卡片的年齡層一樣）：下限往下取到格子開頭（最低 18）、上限往上取到格子結尾。
    // 交友條件可以填任何整數，不放寬的話，把條件設成 31～31 歲再看誰出現，就試得出別人的出生年（10/29「3A」不公開生日；
    // 首頁推薦原本就沒放寬，11/4 兩面審查抓到）。
    public static function preferenceFilter(?self $p): array
    {
        $min = $p?->pref_age_min;
        $max = $p?->pref_age_max;

        return [
            'gender' => in_array($p?->pref_gender, ['男', '女'], true) ? $p->pref_gender : null,
            'place' => ($p?->pref_place ?? '') !== '' ? $p->pref_place : null,
            'age_min' => $min ? max(18, intdiv($min, 5) * 5) : null,
            'age_max' => $max ? intdiv($max, 5) * 5 + 4 : null,
        ];
    }

    // 符合某人交友條件的個人檔案（$pref 是「看的人」的 profile）
    public function scopeMatchesPreferences(\Illuminate\Database\Eloquent\Builder $q, ?self $pref): \Illuminate\Database\Eloquent\Builder
    {
        $f = self::preferenceFilter($pref);

        return $q->when($f['gender'], fn ($w, $g) => $w->where('gender', $g))
            ->when($f['place'], fn ($w, $pl) => $w->where('live_place', $pl))
            ->ageBetween($f['age_min'], $f['age_max']);
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
