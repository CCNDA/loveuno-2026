<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\EmailChanged;
use App\Notifications\VerifyNewEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// 改 Email（設計稿 PageMemberSettings「E-mail〔修改〕」）：輸入新信箱＋密碼 → 寄確認信到新信箱 → 點了才換，
// 換好後寄通知到舊信箱（被盜用時本人才知道）。
class EmailChangeController extends Controller
{
    public function request(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('email', [
            'new_email' => ['required', 'string', 'email', 'max:100'],
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => '密碼不正確。',
        ], ['new_email' => '新的電子郵件', 'current_password' => '目前的密碼']);
        $user = $request->user();
        $new = Str::lower($data['new_email']);
        if ($new === Str::lower($user->email)) {
            throw ValidationException::withMessages(['new_email' => '這就是您現在的電子郵件。'])->errorBag('email');
        }
        // 已經有人用了：不說是誰；一樣顯示「已寄出」，免得被拿來試哪些信箱有註冊
        $taken = User::whereRaw('LOWER(email) = ?', [$new])->exists();
        if (! RateLimiter::attempt('email-change:' . $user->id, 3, fn () => true, 86400)) {
            throw ValidationException::withMessages(['new_email' => '今天寄太多次了，明天再試。'])->errorBag('email');
        }
        if (! $taken) {
            $user->forceFill(['email_pending' => $new])->save();
            $url = URL::temporarySignedRoute('email.change.confirm', now()->addMinutes(60), ['user' => $user->id, 'hash' => sha1($new)]);
            Notification::route('mail', $new)->notify(new VerifyNewEmail($user->name, $url));
        }

        return redirect()->to(route('center.account') . '#settings-title')
            ->with('status', '確認信已經寄到新的電子郵件（60 分鐘內有效），點了信裡的連結才會換。');
    }

    // 信裡的連結：要登入同一個帳號、簽章沒過期、hash 對得上待換的信箱
    public function confirm(Request $request, User $user, string $hash): RedirectResponse
    {
        $me = $request->user();
        abort_unless($me && $me->is($user) && $user->email_pending && hash_equals(sha1($user->email_pending), $hash), 404);
        if (User::whereRaw('LOWER(email) = ?', [$user->email_pending])->whereKeyNot($user->id)->exists()) {
            $user->forceFill(['email_pending' => null])->save();

            return redirect()->route('center.account')->with('status', '這個電子郵件剛剛被別的帳號用了，沒有換。');
        }
        $old = $user->email;
        $user->forceFill(['email' => $user->email_pending, 'email_pending' => null, 'email_verified_at' => now()])->save();
        Notification::route('mail', $old)->notify(new EmailChanged($user->name));

        return redirect()->to(route('center.account') . '#settings-title')->with('status', '電子郵件換好了，下次請用新的電子郵件登入。');
    }
}
