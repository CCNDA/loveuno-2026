<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Support\Points;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 編輯自己的個人檔案（年底開放要用；11/8 示範帳號也要自己填自介，熊叔叔 10/29「直接建檔註冊」）
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'profile' => $user->profile ?? new Profile(['user_id' => $user->id]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20'],
            'live_place' => ['nullable', 'string', 'max:20'],
            'job' => ['nullable', 'string', 'max:20'],
            'church' => ['nullable', 'string', 'max:100'],
            'church_visible' => ['nullable', 'boolean'],
            'intro' => ['nullable', 'string', 'max:2000'],
            'hope' => ['nullable', 'string', 'max:1000'],
            // 生日（月、日）：只用來提醒好友，要嘛兩個都填、要嘛都不填
            'birth_month' => ['nullable', 'integer', 'between:1,12', 'required_with:birth_day'],
            'birth_day' => ['nullable', 'integer', 'between:1,31', 'required_with:birth_month'],
        ], [
            'birth_month.required_with' => '生日的月和日要一起選（或都不選）。',
            'birth_day.required_with' => '生日的月和日要一起選（或都不選）。',
        ], [
            'name' => '暱稱', 'live_place' => '居住地', 'job' => '職業', 'church' => '教會',
            'intro' => '自我介紹', 'hope' => '期待認識的人', 'birth_month' => '生日的月', 'birth_day' => '生日的日',
        ]);
        // 2/30、4/31 這種日子不存在（用閏年 2000 檢查，2/29 可以）
        if (! empty($data['birth_month']) && ! checkdate((int) $data['birth_month'], (int) $data['birth_day'], 2000)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['birth_day' => sprintf('%d 月沒有 %d 日。', $data['birth_month'], $data['birth_day'])]);
        }

        $user = $request->user();
        $user->forceFill(['name' => trim($data['name'])])->save();
        // 性別、出生年註冊時填，這裡不給改（避免年齡層、篩選被亂改；要改請找管理員）
        Profile::updateOrCreate(['user_id' => $user->id], [
            'live_place' => trim((string) ($data['live_place'] ?? '')),
            'job' => trim((string) ($data['job'] ?? '')),
            'church' => trim((string) ($data['church'] ?? '')),
            'church_visible' => $request->boolean('church_visible'),
            'intro' => $data['intro'] ?? null,
            'hope' => $data['hope'] ?? null,
            'birth_month' => $data['birth_month'] ?? null,
            'birth_day' => $data['birth_day'] ?? null,
        ]);

        Points::checkTasks($user); // 基本資料、戀愛履歷（自我介紹）填好就給點

        // 直接回自己的檔案頁（經過 /me 再轉一次，提示訊息會在中途被用掉）
        return redirect()->route('members.show', $user)->with('status', '個人檔案存好了。');
    }
}
