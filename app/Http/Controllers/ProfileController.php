<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Support\Bible;
use App\Support\Points;
use App\Support\ProfileOptions as Opt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// 編輯自己的個人檔案：照設計稿 PageMemberProfile 分成「個人資料」（ProfileForm）和「自我介紹」（IntroductionForm）兩頁。
// 性別、出生年註冊時填，這裡不給改（避免年齡層、篩選被亂改；要改請找管理員）。
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', $this->viewData($request));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(...$this->profileRules($request));
        $this->checkBirthday($data);

        $user = $request->user();
        $user->forceFill(['name' => trim($data['name'])])->save();
        Profile::updateOrCreate(['user_id' => $user->id], self::profileValues($data, array_keys(self::PROFILE_FIELDS)));
        Points::checkTasks($user); // 基本資料填好就給點

        // 直接回自己的檔案頁（經過 /me 再轉一次，提示訊息會在中途被用掉）
        return redirect()->route('members.show', $user)->with('status', '個人資料存好了。');
    }

    // 個人資料欄位怎麼存：text＝去頭尾空白的字串、num＝整數或 null
    private const PROFILE_FIELDS = [
        'real_name' => 'text', 'home_phone' => 'text', 'live_place' => 'text', 'job' => 'text',
        'birth_month' => 'num', 'birth_day' => 'num', 'faith' => 'text', 'marital' => 'text', 'children' => 'text',
        'education' => 'text', 'blood_type' => 'text', 'height' => 'num', 'weight' => 'num',
        'smoking' => 'text', 'drinking' => 'text', 'family_planning' => 'text',
    ];

    private static function profileValues(array $data, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = self::PROFILE_FIELDS[$k] === 'num'
                ? (isset($data[$k]) && $data[$k] !== '' ? (int) $data[$k] : null)
                : self::trimmed($data, $k);
        }

        return $out;
    }

    // 2/30、4/31 這種日子不存在（用閏年 2000 檢查，2/29 可以）
    private function checkBirthday(array $data): void
    {
        if (! empty($data['birth_month']) && ! empty($data['birth_day']) && ! checkdate((int) $data['birth_month'], (int) $data['birth_day'], 2000)) {
            throw ValidationException::withMessages(['birth_day' => sprintf('%d 月沒有 %d 日。', $data['birth_month'], $data['birth_day'])]);
        }
    }

    private function profileRules(Request $request): array
    {
        $in = fn (array $list) => Rule::in($list);

        return [[
            'name' => ['required', 'string', 'max:20'],
            'real_name' => ['nullable', 'string', 'max:30'],
            'home_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-() #]*$/'],
            'live_place' => ['nullable', 'string', 'max:20'],
            'job' => ['nullable', 'string', 'max:20'],
            // 生日（月、日）：只用來提醒好友，要嘛兩個都填、要嘛都不填
            'birth_month' => ['nullable', 'integer', 'between:1,12', 'required_with:birth_day'],
            'birth_day' => ['nullable', 'integer', 'between:1,31', 'required_with:birth_month'],
            'faith' => ['required', $in(Opt::FAITH)],
            'marital' => ['required', $in(Opt::maritalFor((string) $request->user()->profile?->marital))],
            'children' => ['required', $in(Opt::CHILDREN)],
            'education' => ['required', $in(Opt::EDUCATION)],
            'blood_type' => ['required', $in(Opt::BLOOD)],
            'height' => ['nullable', 'integer', 'between:120,230'],
            'weight' => ['nullable', 'integer', 'between:30,200'],
            'smoking' => ['required', $in(Opt::SMOKING)],
            'drinking' => ['required', $in(Opt::DRINKING)],
            'family_planning' => ['nullable', $in(Opt::FAMILY_PLANNING)],
        ], [
            'birth_month.required_with' => '生日的月和日要一起選（或都不選）。',
            'birth_day.required_with' => '生日的月和日要一起選（或都不選）。',
            'home_phone.regex' => '住家電話只能有數字和 + - ( ) # 空格。',
        ], [
            'name' => '暱稱', 'real_name' => '真實姓名', 'home_phone' => '住家電話', 'live_place' => '居住地', 'job' => '職業',
            'birth_month' => '生日的月', 'birth_day' => '生日的日', 'faith' => '信仰', 'marital' => '婚姻狀況', 'children' => '子女人數',
            'education' => '教育程度', 'blood_type' => '血型', 'height' => '身高', 'weight' => '體重', 'smoking' => '是否抽菸',
            'drinking' => '是否喝酒', 'family_planning' => '計劃生育',
        ]];
    }

    // 即時存檔（熊叔叔 11/5「要走 rest api 即時儲存」）：PATCH，只驗證、只存這次送來的欄位
    public function patchProfile(Request $request): JsonResponse
    {
        [$rules, $messages, $attributes] = $this->profileRules($request);
        $keys = self::sentKeys($request, $rules);
        // 生日的月和日是一組：只送一個時，另一個從資料庫補，一起驗（小布 11/5：只送 birth_day=31 會存成 2 月 31 日）
        $keys = self::withGroup($request, $keys, ['birth_month', 'birth_day'], Profile::where('user_id', $request->user()->id)->first()); // 從資料庫讀現值，不用可能過期的關聯
        $data = $request->validate(self::only($rules, $keys), $messages, $attributes);
        if (in_array('birth_month', $keys, true)) {
            $this->checkBirthday($data);
        }
        $user = $request->user();
        if (in_array('name', $keys, true)) {
            $user->forceFill(['name' => trim($data['name'])])->save();
        }
        $fields = array_values(array_intersect($keys, array_keys(self::PROFILE_FIELDS)));
        if ($fields) {
            Profile::updateOrCreate(['user_id' => $user->id], self::profileValues($data, $fields));
        }
        Points::checkTasks($user);

        return self::saved($keys);
    }

    public function patchIntro(Request $request): JsonResponse
    {
        [$rules, $messages, $attributes] = $this->introRules();
        $keys = self::sentKeys($request, $rules);
        // 經文的書卷、章、節是一組：只送一個時，其他從資料庫補，一起驗、經文一起重算（小布 11/5：只送 verse_verse=99 會存成跟經文不符）
        $keys = self::withGroup($request, $keys, ['verse_book', 'verse_chapter', 'verse_verse'], Profile::where('user_id', $request->user()->id)->first()); // 從資料庫讀現值，不用可能過期的關聯
        $data = $request->validate(self::only($rules, $keys), $messages, $attributes);
        $this->checkVerse($data);
        if (in_array('verse_book', $keys, true)) {
            $keys = array_values(array_unique([...$keys, 'verse_text'])); // 經文跟著書卷章節一起存
        }
        $values = array_intersect_key(self::introValues($request, $data), array_flip($keys));
        Profile::updateOrCreate(['user_id' => $request->user()->id], $values);
        Points::checkTasks($request->user());

        return self::saved($keys);
    }

    // 一組欄位（彼此要一起驗的）只送了其中幾個：沒送的從資料庫的現值補進 request，整組一起驗、一起存
    public static function withGroup(Request $request, array $keys, array $group, ?Profile $profile): array
    {
        if (! array_intersect($keys, $group)) {
            return $keys;
        }
        $fill = [];
        foreach ($group as $g) {
            if (! $request->exists($g)) {
                $fill[$g] = $profile?->{$g};
            }
        }
        $request->merge($fill);

        return array_values(array_unique([...$keys, ...$group]));
    }

    // 這次送了哪些「認得的」欄位；一個都沒有就是 422（不認得的欄位不會被存）
    private static function sentKeys(Request $request, array $rules): array
    {
        $known = array_unique(array_map(fn ($k) => explode('.', $k)[0], array_keys($rules)));
        $keys = array_values(array_intersect($known, array_keys($request->except(['_token', '_method']))));
        if (! $keys) {
            throw ValidationException::withMessages(['fields' => '沒有可以存的欄位。']);
        }

        return $keys;
    }

    // 規則只留這次送來的欄位（含 languages.* 這種子規則）
    private static function only(array $rules, array $keys): array
    {
        return array_filter($rules, fn ($k) => in_array(explode('.', $k)[0], $keys, true), ARRAY_FILTER_USE_KEY);
    }

    private static function saved(array $keys): JsonResponse
    {
        return response()->json(['saved' => $keys, 'at' => now()->shown()->format('H:i')]);
    }

    public function editIntro(Request $request): View
    {
        return view('profile.intro', $this->viewData($request));
    }

    public function updateIntro(Request $request): RedirectResponse
    {
        $data = $request->validate(...$this->introRules());
        $this->checkVerse($data);
        $user = $request->user();
        Profile::updateOrCreate(['user_id' => $user->id], self::introValues($request, $data));
        Points::checkTasks($user);

        return redirect()->route('members.show', ['user' => $user, 'tab' => 'intro'])->with('status', '自我介紹存好了。');
    }

    private static function introValues(Request $request, array $data): array
    {
        $text = fn (string $k) => self::trimmed($data, $k);

        return [
            'headline' => $text('headline'),
            'church' => $text('church'), 'church_visible' => $request->boolean('church_visible'),
            'verse_book' => $text('verse_book'),
            'verse_chapter' => $data['verse_chapter'] ?? null, 'verse_verse' => $data['verse_verse'] ?? null,
            // 經文從聖經帶出來，不收使用者打的字（熊叔叔 11/5「聖經要自己帶出來」）
            'verse_text' => ($data['verse_book'] ?? '') !== '' ? (string) Bible::verse($data['verse_book'], (int) $data['verse_chapter'], (int) $data['verse_verse']) : '',
            // 照選項本來的順序存，同一個不會存兩次
            'languages' => array_values(array_intersect(Opt::LANGUAGES, $data['languages'] ?? [])),
            'hobbies' => array_values(array_intersect(Opt::HOBBIES, $data['hobbies'] ?? [])),
            'sports' => array_values(array_intersect(Opt::SPORTS, $data['sports'] ?? [])),
            'intro' => $data['intro'] ?? null, 'hope' => $data['hope'] ?? null,
        ];
    }

    private function introRules(): array
    {
        $picks = ['array', 'max:' . Opt::MAX_PICKS]; // 「請選擇1-3種」

        return [[
            'headline' => ['required', 'string', 'max:50'],
            'church' => ['nullable', 'string', 'max:100'],
            'church_visible' => ['nullable', 'boolean'],
            'verse_book' => ['nullable', Rule::in(Opt::BIBLE_BOOKS)],
            'verse_chapter' => ['nullable', 'integer', 'between:1,150', 'required_with:verse_book'],
            'verse_verse' => ['nullable', 'integer', 'between:1,176', 'required_with:verse_book'],
            'languages' => ['required', ...$picks], 'languages.*' => [Rule::in(Opt::LANGUAGES)],
            'hobbies' => ['required', ...$picks], 'hobbies.*' => [Rule::in(Opt::HOBBIES)],
            'sports' => ['nullable', ...$picks], 'sports.*' => [Rule::in(Opt::SPORTS)],
            // 設計稿「請簡短的寫一篇30字以上的自我介紹」
            'intro' => ['required', 'string', 'min:30', 'max:2000'],
            'hope' => ['nullable', 'string', 'max:1000'],
        ], [
            'languages.max' => '慣用語言最多選 3 種。',
            'hobbies.max' => '興趣最多選 3 種。',
            'sports.max' => '喜愛的運動最多選 3 種。',
            'intro.min' => '自我介紹請寫 30 個字以上。',
            'languages.required' => '請選擇 1～3 種慣用語言。',
            'hobbies.required' => '請選擇 1～3 種興趣。',
            'verse_chapter.required_with' => '選了書卷，請一起填「章」。',
            'verse_verse.required_with' => '選了書卷，請一起填「節」。',
        ], [
            'headline' => '個人標題', 'church' => '教會名稱', 'verse_book' => '聖經書卷', 'verse_chapter' => '章', 'verse_verse' => '節',
            'verse_text' => '經文', 'languages' => '慣用語言', 'hobbies' => '興趣', 'sports' => '喜愛的運動', 'intro' => '自我介紹', 'hope' => '期待認識的人',
        ]];
    }

    private static function trimmed(array $data, string $key): string
    {
        return trim((string) ($data[$key] ?? ''));
    }

    private function viewData(Request $request): array
    {
        $user = $request->user();

        return ['user' => $user, 'profile' => $user->profile ?? new Profile(['user_id' => $user->id])];
    }

    private function checkVerse(array $data): void
    {
        if (($data['verse_book'] ?? '') !== '' && Bible::verse($data['verse_book'], (int) $data['verse_chapter'], (int) $data['verse_verse']) === null) {
            throw ValidationException::withMessages(['verse_verse' => sprintf('%s 沒有 %d 章 %d 節，請再看一下。', $data['verse_book'], $data['verse_chapter'], $data['verse_verse'])]);
        }
    }

    // GET /bible/verse?book=約翰福音&chapter=3&verse=16 → {"text": "…"}（查不到 404）
    public function bibleVerse(Request $request): JsonResponse
    {
        $text = Bible::verse((string) $request->query('book', ''), (int) $request->query('chapter'), (int) $request->query('verse'));

        return $text === null ? response()->json(['text' => null], 404) : response()->json(['text' => $text]);
    }
}
