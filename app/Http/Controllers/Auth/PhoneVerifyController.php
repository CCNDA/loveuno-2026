<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Sms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// 手機認證（熊叔叔 10/29「1A」：會員啟用要電郵認證＋手機認證；「電郵認證後，回來接著要驗證手機，如無法收到簡訊可以EMAIL跟我們聯繫」）。
// 流程：輸入手機 → 寄 6 碼認證碼（10 分鐘內有效）→ 輸入認證碼 → 完成。照舊站 phone_certification*.php。
class PhoneVerifyController extends Controller
{
    public const CODE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->phone_verified_at) {
            return redirect()->route('diaries.index');
        }

        return view('auth.verify-phone', [
            'pending' => $user->phone_pending && $user->phone_code_expires_at?->isFuture() ? $user->phone_pending : null,
            'minutes' => self::CODE_MINUTES,
            'contact' => config('loveuno.contact_email'),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->phone_verified_at !== null, 404);
        $request->merge(['phone' => preg_replace('/[\s-]/', '', (string) $request->input('phone'))]);
        $data = $request->validate([
            'phone' => ['required', 'regex:/^09\d{8}$/'],
        ], [
            'phone.required' => '請輸入手機號碼。',
            'phone.regex' => '請輸入台灣的手機號碼，10 碼，09 開頭，例如 0912345678。',
        ], ['phone' => '手機號碼']);
        $phone = $data['phone'];

        // 一支手機一個帳號（別人已經驗證過的不能再用；不說是誰）
        if (User::where('phone', $phone)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['phone' => '這支手機已經用在別的帳號了。如果是您本人的，請寫信跟我們聯繫。']);
        }
        // 同一個帳號 60 秒內只寄一次、一天最多 5 次（簡訊要錢）
        foreach ([['phone-send-min:' . $user->id, 1, 60], ['phone-send-day:' . $user->id, 5, 86400]] as [$key, $max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages(['phone' => sprintf('認證碼寄太多次了，請 %d 秒後再試；一直收不到的話，請寫信跟我們聯繫。', RateLimiter::availableIn($key))]);
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // 先寄簡訊再寫資料庫：寄不出去就不動狀態（舊站也是這個順序）
        $result = Sms::send($phone, sprintf('CCNDA簡訊：愛諾園手機認證碼 %s，%d 分鐘內有效。', $code, self::CODE_MINUTES), self::CODE_MINUTES * 60);
        if (! $result['ok']) {
            throw ValidationException::withMessages(['phone' => $result['message']]);
        }
        RateLimiter::hit('phone-send-min:' . $user->id, 60);
        RateLimiter::hit('phone-send-day:' . $user->id, 86400);
        $user->forceFill([
            'phone_pending' => $phone,
            'phone_code_hash' => Hash::make($code),
            'phone_code_expires_at' => now()->addMinutes(self::CODE_MINUTES),
            'phone_code_attempts' => 0,
        ])->save();

        return redirect()->route('phone.notice')->with('status', sprintf('認證碼已經用簡訊寄到 %s，%d 分鐘內有效。', $phone, self::CODE_MINUTES));
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->phone_verified_at !== null, 404);
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => '請輸入簡訊裡的 6 碼認證碼。',
            'code.digits' => '認證碼是 6 個數字。',
        ], ['code' => '認證碼']);

        if (! $user->phone_pending || ! $user->phone_code_expires_at?->isFuture()) {
            throw ValidationException::withMessages(['code' => '認證碼已經過期了，請重新寄一次。']);
        }
        if ($user->phone_code_attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => '輸入錯太多次了，請重新寄一次認證碼。']);
        }
        if (! Hash::check($data['code'], (string) $user->phone_code_hash)) {
            $user->increment('phone_code_attempts');
            $left = self::MAX_ATTEMPTS - $user->phone_code_attempts;
            throw ValidationException::withMessages(['code' => $left > 0 ? sprintf('認證碼不對，還可以再試 %d 次。', $left) : '輸入錯太多次了，請重新寄一次認證碼。']);
        }
        if (User::where('phone', $user->phone_pending)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['code' => '這支手機已經用在別的帳號了。如果是您本人的，請寫信跟我們聯繫。']);
        }

        $user->forceFill([
            'phone' => $user->phone_pending,
            'phone_verified_at' => now(),
            'phone_pending' => null,
            'phone_code_hash' => null,
            'phone_code_expires_at' => null,
            'phone_code_attempts' => 0,
        ])->save();

        return redirect()->route('diaries.index')->with('status', '手機認證好了，歡迎來到愛諾園！');
    }

    // 重新輸入手機號碼（打錯號碼時）
    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->phone_verified_at !== null, 404);
        $user->forceFill(['phone_pending' => null, 'phone_code_hash' => null, 'phone_code_expires_at' => null, 'phone_code_attempts' => 0])->save();

        return redirect()->route('phone.notice');
    }
}
