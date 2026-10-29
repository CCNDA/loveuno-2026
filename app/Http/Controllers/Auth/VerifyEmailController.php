<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifyEmailController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('diaries.index');
        }

        return view('auth.verify-email');
    }

    // 信裡的連結：有簽章、有期限（config auth.verification.expire，預設 60 分鐘），而且要是本人登入中
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('diaries.index')->with('status', '電子郵件驗證好了，歡迎加入愛諾園！');
    }

    public function send(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', '驗證信已經重新寄出，請到信箱收信。');
    }
}
