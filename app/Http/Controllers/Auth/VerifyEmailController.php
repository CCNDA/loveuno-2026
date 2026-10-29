<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifyEmailController extends Controller
{
    // 已登入但還沒驗證的人，進會員頁會被帶到這裡
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('diaries.index');
        }

        return view('auth.verify-email');
    }

    // 信裡的連結。註冊後不自動登入（避免洩漏 email 是否已註冊），所以這裡不要求登入；
    // 靠的是：路由有 signed（簽章＋60 分鐘期限）、hash 要等於這個帳號 email 的 sha1。
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);
        abort_unless($user && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route('login')->with('status', '電子郵件驗證好了，請登入。');
    }

    public function send(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', '驗證信已經重新寄出，請到信箱收信。');
    }
}
