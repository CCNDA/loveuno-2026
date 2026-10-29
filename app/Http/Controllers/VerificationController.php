<?php

namespace App\Http\Controllers;

use App\Models\Verification;
use App\Notifications\VerifyJobEmail;
use App\Support\AvatarImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

// 身份認證（設計稿 PageMemberProfile IdentityVerification；照舊站 member/company_certification*.php、id_certification.php、
// church_certification.php）。手機認證是會員啟用的一部分（PhoneVerifyController），這裡只顯示結果。
// 證件、週報照片很敏感：只有管理員看得到，審核完就刪檔。
class VerificationController extends Controller
{
    // 公用信箱不能拿來做職業認證（舊站退件理由「公司Email為公用Email Domain」）
    public const PUBLIC_DOMAINS = ['gmail.com', 'yahoo.com', 'yahoo.com.tw', 'hotmail.com', 'outlook.com', 'live.com', 'msn.com',
        'icloud.com', 'me.com', 'qq.com', '163.com', '126.com', 'pchome.com.tw', 'hinet.net', 'msa.hinet.net', 'seed.net.tw', 'protonmail.com', 'proton.me'];

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('verify.index', [
            'user' => $user,
            'items' => Verification::where('user_id', $user->id)->get()->keyBy('kind'),
            'hasChurch' => trim((string) $user->profile?->church) !== '',
        ]);
    }

    public function storeJob(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('job', [
            'company' => ['required', 'string', 'max:50'],
            'company_url' => ['required', 'url:http,https', 'max:200'],
            'company_email' => ['required', 'email', 'max:100'],
        ], [], ['company' => '公司(學校)名稱', 'company_url' => '公司(學校)首頁網址', 'company_email' => '公司(學校) Email']);
        $domain = Str::lower(Str::after($data['company_email'], '@'));
        if (in_array($domain, self::PUBLIC_DOMAINS, true)) {
            throw ValidationException::withMessages(['company_email' => '請用公司（學校）的 Email，不能用 Gmail、Yahoo 這類公用信箱。'])->errorBag('job');
        }

        $user = $request->user();
        $v = $this->current($user->id, 'job');
        // 認證信一天最多寄 3 封（信會寄到會員自己填的信箱，不能拿來亂寄）
        if (! RateLimiter::attempt('verify-job-mail:' . $user->id, 3, fn () => true, 86400)) {
            throw ValidationException::withMessages(['company_email' => '認證信今天寄太多次了，明天再試。'])->errorBag('job');
        }
        $token = Str::random(40);
        $v->forceFill([
            'status' => 'email_sent', 'company' => trim($data['company']), 'company_url' => trim($data['company_url']),
            'company_email' => Str::lower($data['company_email']), 'email_token_hash' => Hash::make($token),
            'reject_reason' => '', 'reviewed_by' => null, 'reviewed_at' => null,
        ])->save();
        Notification::route('mail', $v->company_email)->notify(new VerifyJobEmail($user->name, route('verify.job.confirm', ['verification' => $v->id, 'token' => $token])));

        return redirect()->route('verify.index')->with('status', '認證信已經寄到您填的公司（學校）信箱，請依照信中的指示完成認證（3 天內有效）。沒收到的話，請看一下垃圾郵件匣。');
    }

    // 信裡的連結：不用登入（可能在公司電腦上點）。打開只顯示「確認」按鈕，按了（POST）才算——
    // 公司信箱的連結掃描器（Safe Links 之類）會自己打開連結，不能一打開就算數。確認碼 3 天內有效、用過就失效。
    public function showConfirmJob(Verification $verification, string $token): View
    {
        $this->checkToken($verification, $token);

        return view('verify.confirm-job', ['verification' => $verification, 'token' => $token]);
    }

    public function confirmJob(Verification $verification, string $token): RedirectResponse
    {
        $this->checkToken($verification, $token);
        $verification->forceFill(['status' => 'pending', 'email_token_hash' => null])->save();

        return redirect()->route('login')->with('status', '您的 Email 已經通過認證，請等待 3-5 個工作天，管理員確認後就完成職業認證。');
    }

    private function checkToken(Verification $verification, string $token): void
    {
        abort_unless($verification->kind === 'job' && $verification->status === 'email_sent'
            && $verification->email_token_hash && $verification->updated_at->gt(now()->subDays(3))
            && Hash::check($token, $verification->email_token_hash), 404);
    }

    public function storeId(Request $request): RedirectResponse
    {
        return $this->storePhoto($request, 'id');
    }

    public function storeChurch(Request $request): RedirectResponse
    {
        abort_unless(trim((string) $request->user()->profile?->church) !== '', 404);

        return $this->storePhoto($request, 'church');
    }

    private function storePhoto(Request $request, string $kind): RedirectResponse
    {
        $request->validateWithBag($kind, [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:min_width=300,min_height=300,max_width=8000,max_height=8000'],
        ], [
            'photo.mimes' => '請選 JPG 或 PNG 的照片。',
            'photo.max' => '照片太大了，請選 5MB 以下的。',
            'photo.dimensions' => '照片太小或太大了（寬、高要在 300～8000 像素之間）。',
        ], ['photo' => '照片']);
        try {
            $jpeg = AvatarImage::fit($request->file('photo')->getRealPath(), 1600); // 要看得清楚字
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['photo' => '這個檔案讀不出來，請換一張照片試試看。'])->errorBag($kind);
        }

        $v = $this->current($request->user()->id, $kind);
        $old = (string) $v->file_path; // 新建的還沒存過，是 null
        $path = 'verify/' . Str::random(40) . '.jpg';
        Storage::disk('avatars')->put($path, $jpeg); // 先寫檔案，再寫資料；資料存失敗就把剛寫的檔刪掉
        try {
            $v->forceFill(['status' => 'pending', 'file_path' => $path, 'reject_reason' => '', 'reviewed_by' => null, 'reviewed_at' => null])->save();
        } catch (\Throwable $e) {
            self::deleteQuietly($path);
            throw $e;
        }
        if ($old !== '' && $old !== $path) {
            self::deleteQuietly($old);
        }

        return redirect()->route('verify.index')->with('status', '上傳完成，請等待 3-5 個工作天人工審核。這張照片只有管理員看得到，審核完就會刪掉。');
    }

    // 刪檔失敗不能讓頁面 500（資料已經改好了）；記下來，之後清
    public static function deleteQuietly(string $path): void
    {
        try {
            Storage::disk('avatars')->delete($path);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function current(int $userId, string $kind): Verification
    {
        $v = Verification::where('user_id', $userId)->where('kind', $kind)->first();
        if ($v && $v->status === 'approved') {
            throw ValidationException::withMessages([$kind === 'job' ? 'company' : 'photo' => '這一項已經通過認證了。'])->errorBag($kind);
        }
        if (! $v) {
            $v = new Verification;
            $v->forceFill(['user_id' => $userId, 'kind' => $kind]);
        }

        return $v;
    }
}
