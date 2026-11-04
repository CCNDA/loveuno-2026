<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Support\LoveHistory;
use App\Support\Points;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// 戀愛履歷（熊叔叔 10/29「3A」：照舊站 member/profile3.php 做問卷）。選擇題必填、文字題可以不填（跟舊站一樣）。
class LoveHistoryController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.love', ['answers' => $request->user()->profile?->love_history ?? []]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        $names = [];
        foreach (LoveHistory::QUESTIONS as $k => [$label, $options]) {
            $rules[$k] = ['required', Rule::in($options)];
            $names[$k] = $label;
        }
        foreach (LoveHistory::TEXTS as $k => [$label, $max]) {
            $rules[$k] = ['nullable', 'string', 'max:' . $max];
            $names[$k] = $label;
        }
        $data = $request->validate($rules, [], $names);

        $answers = [];
        foreach (array_merge(array_keys(LoveHistory::QUESTIONS), array_keys(LoveHistory::TEXTS)) as $k) {
            $answers[$k] = trim((string) ($data[$k] ?? ''));
        }
        $user = $request->user();
        Profile::updateOrCreate(['user_id' => $user->id], ['love_history' => $answers]);
        Points::checkTasks($user); // 完成戀愛履歷建檔 +10

        return redirect()->route('members.show', ['user' => $user, 'tab' => 'intro'])->with('status', '戀愛履歷存好了。');
    }
}
