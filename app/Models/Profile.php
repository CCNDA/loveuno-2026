<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'gender', 'birth_year', 'birth_month', 'birth_day', 'live_place', 'church', 'church_visible', 'job', 'intro', 'hope',
    'pref_gender', 'pref_age_min', 'pref_age_max', 'pref_place',
    'pref_marital', 'pref_faith', 'pref_height_min', 'pref_height_max', 'pref_weight_min', 'pref_weight_max',
    'real_name', 'home_phone', 'faith', 'marital', 'children', 'education', 'blood_type', 'height', 'weight', 'smoking', 'drinking',
    'family_planning', 'headline', 'verse_book', 'verse_chapter', 'verse_verse', 'verse_text', 'languages', 'hobbies', 'sports', 'love_history'])]
// 轉成陣列／JSON 時不輸出：生日月日（只拿來算「生日快到了」，10/29「3A」）、真實姓名與住家電話（只有管理員看得到，10/29「1B」）
#[Hidden(['birth_month', 'birth_day', 'real_name', 'home_phone'])]
class Profile extends Model
{
    // 個人化封面 10 張（設計稿 個人相簿 69:6213，由左到右、由上到下）：插圖上的經文出處，給螢幕閱讀器念
    public const COVER_PRESETS = [
        1 => '詩篇 143:8', 2 => '約翰一書 4:16', 3 => '哥林多前書 13:4-5', 4 => '以弗所書 4:2', 5 => '以弗所書 3:16-17',
        6 => '約翰一書 4:12', 7 => '以賽亞書 43:4', 8 => '箴言 3:3-4', 9 => '以弗所書 5:25-26', 10 => '羅馬書 13:8',
    ];

    // 封面插圖上的經文全文（照 Figma 個人相簿 69:6213 的文字）：手機版封面太窄、圖裡的字太小，改用網頁文字顯示（熊叔叔 11/6）
    public const COVER_VERSES = [
        1 => '求你使我清晨得聽你慈愛之言，因我倚靠你；求你使我知道當行的路，因我的心仰望你。',
        2 => '神愛我們的心，我們也知道也信。神就是愛；住在愛裡面的，就是住在神裡面，神也住在他裡面。',
        3 => '愛是恆久忍耐，又有恩慈；愛是不嫉妒；愛是不自誇，不張狂，不做害羞的事，不求自己的益處，不輕易發怒，不計算人的惡。',
        4 => '凡事謙虛、溫柔、忍耐，用愛心互相寬容。',
        5 => '求他按著他豐盛的榮耀，藉著他的靈，叫你們心裡的力量剛強起來，使基督因你們的信，住在你們心裡，叫你們的愛心有根有基。',
        6 => '從來沒有人見過神，我們若彼此相愛，神就住在我們裡面，愛他的心在我們裡面得以完全了。',
        7 => '因我看你為寶為尊；又因我愛你，所以我使人代替你，使列邦人替換你的生命。',
        8 => '不可使慈愛、誠實離開你，要繫在你頸項上，刻在你心版上。這樣，你必在神和世人眼前蒙恩寵，有聰明。',
        9 => '你們作丈夫的，要愛你們的妻子，正如基督愛教會，為教會捨己。要用水藉著道把教會洗淨，成為聖潔。',
        10 => '凡事都不可虧欠人，惟有彼此相愛要常以為虧欠，因為愛人的就完全了律法。',
    ];

    // 手機版封面：經文疊在插圖上的位置（left／center／right、離邊幾 %）與插圖下面補的底色（熊叔叔 11/6 選 C）
    public const COVER_LAYOUT = [
        1 => ['center', 0, 'linear-gradient(90deg, #c5c7e2 50%, #fff7e6 50%)'],
        2 => ['center', 0, 'linear-gradient(90deg, #f9e3c6 50%, #d9e8e1 50%)'],
        3 => ['left', 8.3, '#fff1d0'],
        4 => ['left', 8.7, '#c3ecec'],
        5 => ['left', 8.3, '#e1e7c3'],
        6 => ['center', 0, 'linear-gradient(90deg, #fff7e6 50%, #f9e3c6 50%)'],
        7 => ['left', 8.3, '#fff7e6'],
        8 => ['right', 8.3, '#c2ebeb'],
        9 => ['left', 8.3, '#f4e3d3'],
        10 => ['right', 7.6, '#f7d8e9'],
    ];

    public function coverPreset(): int
    {
        return isset(self::COVER_PRESETS[(int) $this->cover_preset]) ? (int) $this->cover_preset : 1;
    }

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
            '年齡' => $this->ageLabel(),
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

    // 用出生年篩年齡（只看年，不看生日月日；顯示用的實際年齡是 age()）：認識朋友的條件搜尋、日記牆的好友推薦共用
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
    // 身高、體重也一樣：別人的身高體重不公開，條件放寬到 5 公分／5 公斤一格，設成 170～170 試不出別人的身高（11/5）。
    // 想認識：2026-11-05 熊叔叔「直接從個人檔案先判斷，預設就是選擇異性」——沒有存過明確的性別，就配個人資料性別的異性。
    public static function preferenceFilter(?self $p): array
    {
        $min = $p?->pref_age_min;
        $max = $p?->pref_age_max;
        $down = fn (?int $v) => $v ? intdiv($v, 5) * 5 : null;
        $up = fn (?int $v) => $v ? intdiv($v, 5) * 5 + 4 : null;

        return [
            'gender' => in_array($p?->pref_gender, ['男', '女'], true) ? $p->pref_gender : (['男' => '女', '女' => '男'][$p?->gender ?? ''] ?? null),
            'place' => ($p?->pref_place ?? '') !== '' ? $p->pref_place : null,
            'age_min' => $min ? max(18, intdiv($min, 5) * 5) : null,
            'age_max' => $max ? intdiv($max, 5) * 5 + 4 : null,
            'marital' => ($p?->pref_marital ?? '') !== '' ? $p->pref_marital : null,
            'faith' => ($p?->pref_faith ?? '') !== '' ? $p->pref_faith : null,
            'height_min' => $down($p?->pref_height_min),
            'height_max' => $up($p?->pref_height_max),
            'weight_min' => $down($p?->pref_weight_min),
            'weight_max' => $up($p?->pref_weight_max),
        ];
    }

    // 會員自己填過任何一項交友條件（不算照性別自動配的異性）：認識朋友「還沒設」的判斷用
    public function hasExplicitPreferences(): bool
    {
        foreach (['pref_gender', 'pref_place', 'pref_marital', 'pref_faith'] as $k) {
            if ((string) $this->{$k} !== '') {
                return true;
            }
        }
        foreach (['pref_age_min', 'pref_age_max', 'pref_height_min', 'pref_height_max', 'pref_weight_min', 'pref_weight_max'] as $k) {
            if ($this->{$k} !== null) {
                return true;
            }
        }

        return false;
    }

    // 符合某人交友條件的個人檔案（$pref 是「看的人」的 profile）
    public function scopeMatchesPreferences(\Illuminate\Database\Eloquent\Builder $q, ?self $pref): \Illuminate\Database\Eloquent\Builder
    {
        $f = self::preferenceFilter($pref);

        return $q->when($f['gender'], fn ($w, $g) => $w->where('gender', $g))
            ->when($f['place'], fn ($w, $pl) => $w->where('live_place', $pl))
            ->when($f['marital'], fn ($w, $m) => $w->where('marital', $m))
            ->when($f['faith'], fn ($w, $v) => $w->where('faith', $v))
            ->when($f['height_min'], fn ($w, $v) => $w->where('height', '>=', $v))
            ->when($f['height_max'], fn ($w, $v) => $w->where('height', '<=', $v))
            ->when($f['weight_min'], fn ($w, $v) => $w->where('weight', '>=', $v))
            ->when($f['weight_max'], fn ($w, $v) => $w->where('weight', '<=', $v))
            ->ageBetween($f['age_min'], $f['age_max']);
    }

    // 實際年齡（熊叔叔 11/6「個人檔案年齡是真實年齡，不是範圍」；原本只顯示 5 歲一格的年齡層）。
    // 有生日就算到今天滿幾歲，只有出生年就用今年減出生年。生日本身還是不公開（10/29「3A」）
    public function age(): ?int
    {
        if (! $this->birth_year) {
            return null;
        }
        $today = today();
        $age = $today->year - $this->birth_year;
        if ($this->birth_month && $this->birth_day
            && [$today->month, $today->day] < [(int) $this->birth_month, (int) $this->birth_day]) {
            $age--;
        }

        return $age;
    }

    public function ageLabel(): ?string
    {
        $age = $this->age();

        return $age === null ? null : $age . ' 歲';
    }
}
