<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:100'],
            'password' => ['required', 'string', 'max:200'],
        ], [], ['email' => '電子郵件', 'password' => '密碼']);

        // 同一個 email＋IP 一分鐘最多錯 5 次；同一個 IP 不管換幾個 email，一分鐘最多錯 20 次
        $key = Str::lower($data['email']) . '|' . $request->ip();
        $ipKey = 'ip|' . $request->ip();
        foreach ([[$key, 5], [$ipKey, 20]] as [$k, $max]) {
            if (RateLimiter::tooManyAttempts($k, $max)) {
                throw ValidationException::withMessages([
                    'email' => sprintf('嘗試太多次了，請 %d 秒後再試。', RateLimiter::availableIn($k)),
                ]);
            }
        }

        $credentials = ['email' => Str::lower($data['email']), 'password' => $data['password']];
        // 固定花一樣久：email 不存在時不會跑雜湊，回得比較快，可以被拿來猜哪些 email 有註冊
        $ok = (new Timebox)->call(fn () => Auth::attempt($credentials, $request->boolean('remember')), 300_000);
        if (! $ok) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($ipKey, 60);
            // 不說是 email 錯還是密碼錯，避免被拿來試哪些 email 有註冊
            throw ValidationException::withMessages(['email' => '電子郵件或密碼不正確。']);
        }

        // 停權的帳號（舊站搬來的停權會員）：密碼對了也不能登入。只在密碼對的時候才說，不會被拿來試帳號
        if ($request->user()->suspended_at !== null) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => sprintf('這個帳號已經停權。如果有疑問，請寫信到 %s 跟我們聯繫。', config('loveuno.contact_email'))]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        $request->session()->put('script', $request->user()->script ?: 'hant'); // 帳號設定的「顯示語言」

        return redirect()->intended(route('diaries.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        // 這台裝置在這次登入開的推播：登出就刪掉
        $ids = (array) $request->session()->get('push_subscription_ids', []);
        if ($ids && $request->user()) {
            \App\Models\PushSubscription::whereIn('id', $ids)->where('user_id', $request->user()->id)->delete();
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', '已經登出了。');
    }
}
