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

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('diaries.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', '已經登出了。');
    }
}
