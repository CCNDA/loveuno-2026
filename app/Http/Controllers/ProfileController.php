<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Support\Points;
use App\Support\ProfileOptions as Opt;
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
        $in = fn (array $list) => Rule::in($list);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20'],
            'real_name' => ['nullable', 'string', 'max:30'],
            'home_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-() #]*$/'],
            'live_place' => ['nullable', 'string', 'max:20'],
            'job' => ['nullable', 'string', 'max:20'],
            // 生日（月、日）：只用來提醒好友，要嘛兩個都填、要嘛都不填
            'birth_month' => ['nullable', 'integer', 'between:1,12', 'required_with:birth_day'],
            'birth_day' => ['nullable', 'integer', 'between:1,31', 'required_with:birth_month'],
            'faith' => ['nullable', $in(Opt::FAITH)],
            'marital' => ['nullable', $in(Opt::MARITAL)],
            'children' => ['nullable', $in(array_map('strval', array_keys(Opt::CHILDREN)))],
            'education' => ['nullable', $in(Opt::EDUCATION)],
            'blood_type' => ['nullable', $in(Opt::BLOOD)],
            'height' => ['nullable', 'integer', 'between:120,230'],
            'weight' => ['nullable', 'integer', 'between:30,200'],
            'smoking' => ['nullable', $in(Opt::SMOKING)],
            'drinking' => ['nullable', $in(Opt::DRINKING)],
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
        ]);
        // 2/30、4/31 這種日子不存在（用閏年 2000 檢查，2/29 可以）
        if (! empty($data['birth_month']) && ! checkdate((int) $data['birth_month'], (int) $data['birth_day'], 2000)) {
            throw ValidationException::withMessages(['birth_day' => sprintf('%d 月沒有 %d 日。', $data['birth_month'], $data['birth_day'])]);
        }

        $user = $request->user();
        $user->forceFill(['name' => trim($data['name'])])->save();
        $text = fn (string $k) => trim((string) ($data[$k] ?? ''));
        $num = fn (string $k) => isset($data[$k]) && $data[$k] !== '' ? (int) $data[$k] : null;
        Profile::updateOrCreate(['user_id' => $user->id], [
            'real_name' => $text('real_name'), 'home_phone' => $text('home_phone'),
            'live_place' => $text('live_place'), 'job' => $text('job'),
            'birth_month' => $num('birth_month'), 'birth_day' => $num('birth_day'),
            'faith' => $text('faith'), 'marital' => $text('marital'), 'children' => $num('children'),
            'education' => $text('education'), 'blood_type' => $text('blood_type'),
            'height' => $num('height'), 'weight' => $num('weight'),
            'smoking' => $text('smoking'), 'drinking' => $text('drinking'), 'family_planning' => $text('family_planning'),
        ]);
        Points::checkTasks($user); // 基本資料填好就給點

        // 直接回自己的檔案頁（經過 /me 再轉一次，提示訊息會在中途被用掉）
        return redirect()->route('members.show', $user)->with('status', '個人資料存好了。');
    }

    public function editIntro(Request $request): View
    {
        return view('profile.intro', $this->viewData($request));
    }

    public function updateIntro(Request $request): RedirectResponse
    {
        $picks = ['nullable', 'array', 'max:' . Opt::MAX_PICKS]; // 「請選擇1-3種」
        $data = $request->validate([
            'headline' => ['nullable', 'string', 'max:30'],
            'church' => ['nullable', 'string', 'max:100'],
            'church_visible' => ['nullable', 'boolean'],
            'verse_book' => ['nullable', Rule::in(Opt::BIBLE_BOOKS)],
            'verse_chapter' => ['nullable', 'integer', 'between:1,150', 'required_with:verse_book'],
            'verse_verse' => ['nullable', 'integer', 'between:1,176'],
            'verse_text' => ['nullable', 'string', 'max:300'],
            'languages' => $picks, 'languages.*' => [Rule::in(Opt::LANGUAGES)],
            'hobbies' => $picks, 'hobbies.*' => [Rule::in(Opt::HOBBIES)],
            'sports' => $picks, 'sports.*' => [Rule::in(Opt::SPORTS)],
            // 設計稿「請簡短的寫一篇30字以上的自我介紹」：有寫就要 30 字以上（可以先不寫）
            'intro' => ['nullable', 'string', 'min:30', 'max:2000'],
            'hope' => ['nullable', 'string', 'max:1000'],
        ], [
            'languages.max' => '慣用語言最多選 3 種。',
            'hobbies.max' => '興趣最多選 3 種。',
            'sports.max' => '喜愛的運動最多選 3 種。',
            'intro.min' => '自我介紹請寫 30 個字以上（也可以先不寫）。',
            'verse_chapter.required_with' => '選了書卷，請一起填「章」。',
        ], [
            'headline' => '個人標題', 'church' => '教會名稱', 'verse_book' => '聖經書卷', 'verse_chapter' => '章', 'verse_verse' => '節',
            'verse_text' => '經文', 'languages' => '慣用語言', 'hobbies' => '興趣', 'sports' => '喜愛的運動', 'intro' => '自我介紹', 'hope' => '期待認識的人',
        ]);

        $user = $request->user();
        $text = fn (string $k) => trim((string) ($data[$k] ?? ''));
        Profile::updateOrCreate(['user_id' => $user->id], [
            'headline' => $text('headline'),
            'church' => $text('church'), 'church_visible' => $request->boolean('church_visible'),
            'verse_book' => $text('verse_book'),
            'verse_chapter' => $data['verse_chapter'] ?? null, 'verse_verse' => $data['verse_verse'] ?? null,
            'verse_text' => $text('verse_text'),
            // 照選項本來的順序存，同一個不會存兩次
            'languages' => array_values(array_intersect(Opt::LANGUAGES, $data['languages'] ?? [])),
            'hobbies' => array_values(array_intersect(Opt::HOBBIES, $data['hobbies'] ?? [])),
            'sports' => array_values(array_intersect(Opt::SPORTS, $data['sports'] ?? [])),
            'intro' => $data['intro'] ?? null, 'hope' => $data['hope'] ?? null,
        ]);
        Points::checkTasks($user);

        return redirect()->route('members.show', ['user' => $user, 'tab' => 'intro'])->with('status', '自我介紹存好了。');
    }

    private function viewData(Request $request): array
    {
        $user = $request->user();

        return ['user' => $user, 'profile' => $user->profile ?? new Profile(['user_id' => $user->id])];
    }
}
