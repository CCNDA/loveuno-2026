<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

// 會員中心（照 loveuno-web 版型的四個圓形分頁：帳號管理／個人檔案／愛積點／交友條件）
// 個人檔案的編輯與大頭照沿用 ProfileController、PhotoController，這裡只放帳號、愛積點、交友條件。
class CenterController extends Controller
{
    public function account(Request $request): View
    {
        $user = $request->user();
        $blocked = \App\Models\User::whereIn('id', \DB::table('blocks')->where('blocker_id', $user->id)->pluck('blocked_id'))
            ->orderBy('name')->get(['id', 'name']);

        return view('center.account', ['user' => $user, 'blocked' => $blocked]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'max:200', Password::min(10)],
        ], [
            'current_password.current_password' => '目前的密碼不正確。',
        ], ['current_password' => '目前的密碼', 'password' => '新密碼']);

        $user = $request->user();
        // 先存新密碼，再呼叫 logoutOtherDevices：它會先用「傳進去的密碼」核對目前存的雜湊，
        // 核對過了才重算一次雜湊並讓其他裝置的 session 失效（要配 auth.session）。
        // 所以這裡看起來雜湊了兩次，但少了第一次存檔，核對會失敗、丟例外（兩面審查 10/29 討論過）。
        $user->forceFill(['password' => Hash::make($request->input('password'))])->save();
        Auth::logoutOtherDevices($request->input('password'));
        $request->session()->regenerate();

        return redirect()->route('center.account')->with('status', '密碼換好了。其他裝置上的登入已經登出。');
    }

    public function points(Request $request): View
    {
        return view('center.points', ['user' => $request->user()]);
    }

    public function preferences(Request $request): View
    {
        $user = $request->user();

        return view('center.preferences', [
            'profile' => $user->profile ?? new Profile(['user_id' => $user->id]),
            'places' => Profile::where('live_place', '<>', '')->distinct()->orderBy('live_place')->pluck('live_place'),
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pref_gender' => ['nullable', 'in:男,女'],
            'pref_age_min' => ['nullable', 'integer', 'between:18,99'],
            // 只有下限有填才比大小（只填「到 40 歲」不能被誤擋）
            'pref_age_max' => ['nullable', 'integer', 'between:18,99', Rule::when($request->filled('pref_age_min'), 'gte:pref_age_min')],
            'pref_place' => ['nullable', 'string', 'max:20'],
        ], [
            'pref_age_max.gte' => '年齡上限不能比下限小。',
        ], ['pref_gender' => '想認識', 'pref_age_min' => '年齡下限', 'pref_age_max' => '年齡上限', 'pref_place' => '居住地']);

        Profile::updateOrCreate(['user_id' => $request->user()->id], [
            'pref_gender' => $data['pref_gender'] ?? '',
            'pref_age_min' => $data['pref_age_min'] ?? null,
            'pref_age_max' => $data['pref_age_max'] ?? null,
            'pref_place' => trim((string) ($data['pref_place'] ?? '')),
        ]);

        return redirect()->route('center.preferences')->with('status', '交友條件存好了。');
    }
}
