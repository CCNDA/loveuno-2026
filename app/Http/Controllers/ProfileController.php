<?php

namespace App\Http\Controllers;

use App\Models\Profile;
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
        ], [], [
            'name' => '暱稱', 'live_place' => '居住地', 'job' => '職業', 'church' => '教會',
            'intro' => '自我介紹', 'hope' => '期待認識的人',
        ]);

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
        ]);

        return redirect()->route('me')->with('status', '個人檔案存好了。');
    }
}
