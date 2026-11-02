<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Support\AvatarImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

// 回報問題／建議（熊叔叔 11/2 TG 選 fb-A）。登入了就能送（還沒驗證手機的也可以，註冊卡住的人最需要回報）。
// 自動記：會員、從哪一頁按的、瀏覽器、畫面大小；截圖可以不附。
class FeedbackController extends Controller
{
    public function create(Request $request): View
    {
        return view('feedback.create', ['from' => self::safePage((string) $request->query('from', ''))]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Feedback::KINDS))],
            'body' => ['required', 'string', 'min:5', 'max:1000'],
            'screenshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'page' => ['nullable', 'string', 'max:300'],
            'screen' => ['nullable', 'string', 'max:20'],
        ], [
            'kind.required' => '請選擇是問題還是建議。',
            'kind.in' => '請選擇是問題還是建議。',
            'body.min' => '請多寫一點，至少 5 個字（例如在哪一頁、按了什麼、看到什麼）。',
            'body.max' => '內容不能超過 1000 字。',
            'screenshot.mimes' => '圖片請選 JPG 或 PNG。',
            'screenshot.max' => '圖片太大了，請選 5MB 以下的。',
            'screenshot.dimensions' => '圖片的寬、高不能超過 6000 像素。',
        ], ['kind' => '類型', 'body' => '內容', 'screenshot' => '截圖']);

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

        $page = self::safePage((string) ($data['page'] ?? ''));
        $fb = new Feedback(['kind' => $data['kind'], 'body' => trim($data['body'])]);
        $fb->forceFill([
            'user_id' => $request->user()->id,
            'page' => $page,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
            'screen' => preg_match('/^\d{2,5}x\d{2,5}$/', (string) ($data['screen'] ?? '')) ? $data['screen'] : '',
            'screenshot_path' => $path,
        ])->save();

        return redirect()->to($page !== '' ? $page : route('home'))->with('status', '謝謝您！我們收到了，會儘快看。');
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
}
