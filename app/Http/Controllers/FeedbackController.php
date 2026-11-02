<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Support\AvatarImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

// 回報問題／建議（熊叔叔 11/2 TG 選 fb-A）。每一頁都能送，沒登入也可以：登入頁、註冊、錯誤頁是年長會友最常卡住的地方
// （兩面審查 11/2）。登入的記會員，沒登入的可以留 email。擋機器人用看不到的欄位（跟聯絡我們一樣）。
// 自動記：從哪一頁按的、瀏覽器、畫面大小、網站版本、字體大小、繁簡；截圖可以不附。
class FeedbackController extends Controller
{
    public function create(Request $request): View
    {
        return view('feedback.create', [
            'from' => self::safePage((string) $request->query('from', '')),
            'maxMb' => self::maxUploadMb(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = self::safePage((string) $request->input('page', ''));
        $back = $page !== '' ? $page : route('home');
        if (trim((string) $request->input('website')) !== '') {
            return redirect()->to($back)->with('status', '謝謝您！我們收到了，會儘快看。'); // 機器人：裝作收到
        }

        $maxKb = self::maxUploadMb() * 1024;
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Feedback::KINDS))],
            'body' => ['required', 'string', 'min:5', 'max:1000'],
            'email' => ['nullable', 'email', 'max:100'],
            'screenshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:' . $maxKb, 'dimensions:max_width=6000,max_height=6000'],
        ], [
            'kind.required' => '請選擇是問題還是建議。',
            'kind.in' => '請選擇是問題還是建議。',
            'body.min' => '請多寫一點，至少 5 個字（例如在哪一頁、按了什麼、看到什麼）。',
            'body.max' => '內容不能超過 1000 字。',
            'email.email' => 'Email 的格式不對，不想留可以空白。',
            'screenshot.mimes' => '圖片請選 JPG 或 PNG。',
            'screenshot.max' => sprintf('圖片太大了，請選 %dMB 以下的。', self::maxUploadMb()),
            // 超過伺服器的上傳上限時，Laravel 只說 uploaded（英文「failed to upload」）
            'screenshot.uploaded' => sprintf('圖片沒有傳上來，可能太大了，請選 %dMB 以下的。', self::maxUploadMb()),
            'screenshot.dimensions' => '圖片的寬、高不能超過 6000 像素。',
        ], ['kind' => '類型', 'body' => '內容', 'email' => 'Email', 'screenshot' => '截圖']);

        $path = null;
        if ($request->hasFile('screenshot')) {
            try {
                $jpeg = AvatarImage::fit($request->file('screenshot')->getRealPath(), 1600); // 跟檢舉截圖一樣，字要看得清楚
            } catch (RuntimeException) {
                throw ValidationException::withMessages(['screenshot' => '這個圖片讀不出來，請換一張試試看。']);
            }
            $path = 'feedback/' . Str::random(40) . '.jpg';
            Storage::disk('avatars')->put($path, $jpeg);
        }

        $user = $request->user();
        $screen = (string) $request->input('screen', '');
        $fb = new Feedback(['kind' => $data['kind'], 'body' => trim($data['body'])]);
        $fb->forceFill([
            'user_id' => $user?->id,
            'email' => $user ? '' : mb_strtolower(trim((string) ($data['email'] ?? ''))),
            'page' => $page,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
            'screen' => preg_match('/^\d{2,5}x\d{2,5}$/', $screen) ? $screen : '', // 頁面上的 JS 填的，不對就不記、不報錯
            'context' => self::context($request),
            'screenshot_path' => $path,
        ])->save();

        return redirect()->to($back)->with('status', '謝謝您！我們收到了，會儘快看。');
    }

    // 只收本站的路徑（/ 開頭、不是 //），不收外部網址：不然「送出後回到原頁」會被拿來轉到別的網站
    public static function safePage(string $page): string
    {
        $page = trim($page);
        if ($page === '' || $page[0] !== '/' || str_starts_with($page, '//') || str_contains($page, '\\') || preg_match('/[\x00-\x1f]/', $page)) {
            return '';
        }

        return mb_substr($page, 0, 300);
    }

    // 截圖上限：5MB，但不超過伺服器的上傳上限（測試站 php.ini 是 2MB）
    public static function maxUploadMb(): int
    {
        return max(1, min(5, intdiv((int) UploadedFile::getMaxFilesize(), 1024 * 1024)));
    }

    // 版本、字體大小、繁簡：版面跑掉常常跟這些有關
    private static function context(Request $request): string
    {
        $file = base_path('VERSION');
        $version = is_readable($file) ? trim((string) file_get_contents($file)) : 'dev';
        $user = $request->user();
        $script = $request->hasSession() ? (string) $request->session()->get('script', $user?->script ?: 'hant') : 'hant';

        return mb_substr(implode('・', array_filter([
            '版本 ' . $version,
            $user ? '字體 ' . $user->font_size : null,
            $script === 'hans' ? '简体' : '繁體',
        ])), 0, 100);
    }
}
