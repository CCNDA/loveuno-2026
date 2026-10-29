<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// 忘記密碼（設計稿 PageForgotPassword／PageResetPassword）。
// 跟註冊一樣不洩漏哪些 email 有註冊：不管有沒有這個會員，畫面都說同一句話、花一樣久。
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:100'],
        ], [], ['email' => '電子郵件']);

        // 回傳值（寄了／沒這個人／60 秒內寄過）都不告訴畫面
        (new Timebox)->call(fn () => Password::sendResetLink(['email' => Str::lower($data['email'])]), 300_000);

        return redirect()->route('password.request')
            ->with('status', '如果這個電子郵件是愛諾園的會員，我們已經寄出重設密碼的信。請到信箱收信，連結 60 分鐘內有效。');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:100'],
            'password' => ['required', 'string', 'confirmed', 'max:200', PasswordRule::min(10)],
        ], [], ['email' => '電子郵件', 'password' => '新密碼']);

        $status = Password::reset(
            ['email' => Str::lower($data['email'])] + $data,
            function (User $user, string $password) {
                // 換密碼雜湊後，其他裝置的登入會被 auth.session 登出；remember_token 也換掉，「記住我」一起失效
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)]);
                // 能從信裡的連結進來，就證明信箱是本人的
                if (! $user->hasVerifiedEmail()) {
                    $user->email_verified_at = now();
                }
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // 連結過期、用過、或 email 跟連結對不上：同一句話，不說是哪一種
            throw ValidationException::withMessages(['email' => '這個重設連結已經失效或不正確，請重新申請一次。']);
        }

        return redirect()->route('login')->with('status', '密碼重設好了，請用新密碼登入。');
    }
}
