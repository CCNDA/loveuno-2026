<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Support\Points;
use App\Support\ProfileOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $blocked = User::whereIn('id', DB::table('blocks')->where('blocker_id', $user->id)->pluck('blocked_id'))
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

    // 愛積點：任務獎勵（預設）／使用紀錄（?tab=records）
    public function points(Request $request): View
    {
        $user = $request->user();
        Points::checkTasks($user); // 以前就填好的（例如舊站匯入、示範會員）第一次打開這頁時補給
        $tab = $request->query('tab') === 'records' ? 'records' : 'tasks';

        $user = $user->fresh();
        $records = $tab === 'records'
            ? DB::table('point_transactions')->where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->limit(100)->get()
            : collect();
        $running = $user->points;
        foreach ($records as $r) { // 新的在上面：這一筆之後的餘額＝目前餘額減掉比它新的
            $r->total = $running;
            $running -= $r->amount;
        }

        return view('center.points', [
            'user' => $user,
            'tab' => $tab,
            'status' => Points::taskStatus($user),
            'links' => ['profile' => 'profile.edit', 'preferences' => 'center.preferences', 'love' => 'profile.love', 'photo' => 'photo.edit', 'phone' => 'verify.index', 'job' => 'verify.index', 'id' => 'verify.index', 'church' => 'verify.index'],
            'records' => $records,
        ]);
    }

    public function preferences(Request $request): View
    {
        $user = $request->user();

        return view('center.preferences', [
            'profile' => $user->profile ?? new Profile(['user_id' => $user->id]),
            'places' => Profile::where('live_place', '<>', '')->distinct()->orderBy('live_place')->pluck('live_place'),
        ]);
    }

    // 即時存檔（熊叔叔 11/5）：整張交友條件送來，驗證和存法跟 updatePreferences() 一樣，回 JSON
    public function patchPreferences(Request $request): JsonResponse
    {
        // 只送部分欄位時，其他交友條件從資料庫補進來再整張存（小布 11/5：不然沒送的會被清空）
        $p = Profile::where('user_id', $request->user()->id)->first(); // 從資料庫讀現值
        $all = ['pref_gender', 'pref_age_min', 'pref_age_max', 'pref_place', 'pref_marital', 'pref_faith',
            'pref_height_min', 'pref_height_max', 'pref_weight_min', 'pref_weight_max'];
        if (! array_intersect($all, array_keys($request->all()))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['fields' => '沒有可以存的欄位。']);
        }
        $fill = [];
        foreach ($all as $k) {
            if (! $request->exists($k)) {
                $fill[$k] = $p?->{$k};
            }
        }
        $request->merge($fill);
        $this->updatePreferences($request);
        $request->session()->forget('status');

        return response()->json(['saved' => ['preferences'], 'at' => now()->shown()->format('H:i')]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        // 只有下限有填才比大小（只填「到 40 歲」不能被誤擋）
        $upper = fn (string $min) => Rule::when($request->filled($min), 'gte:' . $min);
        $data = $request->validate([
            'pref_gender' => ['nullable', 'in:男,女'],
            'pref_age_min' => ['nullable', 'integer', 'between:18,99'],
            'pref_age_max' => ['nullable', 'integer', 'between:18,99', $upper('pref_age_min')],
            'pref_place' => ['nullable', 'string', 'max:20'],
            'pref_marital' => ['nullable', Rule::in(ProfileOptions::MARITAL)],
            'pref_faith' => ['nullable', Rule::in(ProfileOptions::FAITH)],
            'pref_height_min' => ['nullable', 'integer', 'between:120,230'],
            'pref_height_max' => ['nullable', 'integer', 'between:120,230', $upper('pref_height_min')],
            'pref_weight_min' => ['nullable', 'integer', 'between:30,200'],
            'pref_weight_max' => ['nullable', 'integer', 'between:30,200', $upper('pref_weight_min')],
        ], [
            'pref_age_max.gte' => '年齡上限不能比下限小。',
            'pref_height_max.gte' => '身高上限不能比下限小。',
            'pref_weight_max.gte' => '體重上限不能比下限小。',
        ], ['pref_gender' => '想認識', 'pref_age_min' => '年齡下限', 'pref_age_max' => '年齡上限', 'pref_place' => '居住地區',
            'pref_marital' => '婚姻狀態', 'pref_faith' => '對象信仰', 'pref_height_min' => '身高下限', 'pref_height_max' => '身高上限',
            'pref_weight_min' => '體重下限', 'pref_weight_max' => '體重上限']);

        $profile = Profile::updateOrCreate(['user_id' => $request->user()->id], [
            // 畫面上不再讓人選（照個人資料的性別配異性）；舊的表單或程式送來的才存
            'pref_gender' => $data['pref_gender'] ?? '',
            'pref_age_min' => $data['pref_age_min'] ?? null,
            'pref_age_max' => $data['pref_age_max'] ?? null,
            'pref_place' => trim((string) ($data['pref_place'] ?? '')),
            'pref_marital' => $data['pref_marital'] ?? '',
            'pref_faith' => $data['pref_faith'] ?? '',
            'pref_height_min' => $data['pref_height_min'] ?? null,
            'pref_height_max' => $data['pref_height_max'] ?? null,
            'pref_weight_min' => $data['pref_weight_min'] ?? null,
            'pref_weight_max' => $data['pref_weight_max'] ?? null,
        ]);
        // 存過就算設了，四項都「不限」也一樣（認識朋友沒存過才請他先設定；小霏 11/4 抓到原本會卡成迴圈）
        $profile->forceFill(['preferences_saved_at' => now()])->save();

        Points::checkTasks($request->user());

        return redirect()->route('center.preferences')->with('status', '交友條件存好了。');
    }
}
