<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Sms;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// 手機認證（熊叔叔 10/29「1A」：會員啟用要電郵認證＋手機認證；「電郵認證後，回來接著要驗證手機，如無法收到簡訊可以EMAIL跟我們聯繫」）。
// 流程：輸入手機 → 寄 6 碼認證碼（10 分鐘內有效）→ 輸入認證碼 → 完成。照舊站 phone_certification*.php。
// 已經認證過的人可以「更換手機號碼」（要先輸入密碼；新號碼通過才換掉舊的，中途放棄原本的認證還在，跟舊站一樣）。
class PhoneVerifyController extends Controller
{
    public const CODE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->phone_verified_at && ! $this->pendingOf($user)) {
            return redirect()->route('verify.index');
        }

        return view('auth.verify-phone', [
            'pending' => $this->pendingOf($user),
            'changing' => $user->phone_verified_at !== null,
            'minutes' => self::CODE_MINUTES,
            'contact' => config('loveuno.contact_email'),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        $changing = $user->phone_verified_at !== null;
        $request->merge(['phone' => preg_replace('/[\s-]/', '', (string) $request->input('phone'))]);
        $rules = ['phone' => ['required', 'regex:/^09\d{8}$/']];
        if ($changing) {
            $rules['current_password'] = ['required', 'current_password']; // 換號碼要先輸入密碼（舊站也是）
        }
        $data = $request->validate($rules, [
            'phone.required' => '請輸入手機號碼。',
            'phone.regex' => '請輸入台灣的手機號碼，10 碼，09 開頭，例如 0912345678。',
            'current_password.current_password' => '密碼不正確。',
        ], ['phone' => '手機號碼', 'current_password' => '目前的密碼']);
        $phone = $data['phone'];
        if ($changing && $phone === $user->phone) {
            throw ValidationException::withMessages(['phone' => '這就是您現在認證的號碼。']);
        }
        if (User::where('phone', $phone)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['phone' => '這支手機已經用在別的帳號了。如果是您本人的，請寫信跟我們聯繫。']);
        }

        // 先記次數再寄（寄簡訊要等最久 20 秒，同時送很多個請求也只會寄出上限那麼多封）：
        // 同一個帳號 60 秒一次、一天 5 次；同一支號碼一天 3 次（不能拿來轟炸別人的手機）
        foreach ([['phone-send-min:' . $user->id, 1, 60], ['phone-send-day:' . $user->id, 5, 86400], ['phone-send-number:' . $phone, 3, 86400]] as [$key, $max, $decay]) {
            if (! RateLimiter::attempt($key, $max, fn () => true, $decay)) {
                throw ValidationException::withMessages(['phone' => sprintf('認證碼寄太多次了，請 %d 秒後再試；一直收不到的話，請寫信跟我們聯繫。', RateLimiter::availableIn($key))]);
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // 先寄簡訊再寫資料庫：寄不出去就不動狀態（舊站也是這個順序）
        $result = Sms::send($phone, sprintf('CCNDA簡訊：愛諾園手機認證碼 %s，%d 分鐘內有效。', $code, self::CODE_MINUTES), self::CODE_MINUTES * 60);
        if (! $result['ok']) {
            throw ValidationException::withMessages(['phone' => $result['message']]);
        }
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
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => '請輸入簡訊裡的 6 碼認證碼。',
            'code.digits' => '認證碼是 6 個數字。',
        ], ['code' => '認證碼']);

        if (! $this->pendingOf($user)) {
            throw ValidationException::withMessages(['code' => '認證碼已經過期了，請重新寄一次。']);
        }
        // 先佔一次機會再比對（條件式 UPDATE）：同時送很多個請求，也只有前 5 個會被比對
        $took = User::whereKey($user->id)->where('phone_code_attempts', '<', self::MAX_ATTEMPTS)->increment('phone_code_attempts');
        if (! $took) {
            throw ValidationException::withMessages(['code' => '輸入錯太多次了，請重新寄一次認證碼。']);
        }
        $user->refresh();
        if (! Hash::check($data['code'], (string) $user->phone_code_hash)) {
            $left = self::MAX_ATTEMPTS - $user->phone_code_attempts;
            throw ValidationException::withMessages(['code' => $left > 0 ? sprintf('認證碼不對，還可以再試 %d 次。', $left) : '輸入錯太多次了，請重新寄一次認證碼。']);
        }

        $changing = $user->phone_verified_at !== null;
        try {
            $user->forceFill([
                'phone' => $user->phone_pending,
                'phone_verified_at' => now(),
                'phone_pending' => null,
                'phone_code_hash' => null,
                'phone_code_expires_at' => null,
                'phone_code_attempts' => 0,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            // 兩個人同時驗證同一支手機：後面那位
            throw ValidationException::withMessages(['code' => '這支手機已經用在別的帳號了。如果是您本人的，請寫信跟我們聯繫。']);
        }

        return $changing
            ? redirect()->route('verify.index')->with('status', '手機號碼換好了。')
            : redirect()->route('diaries.index')->with('status', '手機認證好了，歡迎來到愛諾園！');
    }

    // 重新輸入手機號碼（打錯號碼，或換號碼時改變主意）：原本認證過的號碼不受影響
    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['phone_pending' => null, 'phone_code_hash' => null, 'phone_code_expires_at' => null, 'phone_code_attempts' => 0])->save();

        return redirect()->route($user->phone_verified_at ? 'verify.index' : 'phone.notice');
    }

    // 有寄出、還沒過期的待驗證號碼
    private function pendingOf(User $user): ?string
    {
        return $user->phone_pending && $user->phone_code_expires_at?->isFuture() ? $user->phone_pending : null;
    }
}
