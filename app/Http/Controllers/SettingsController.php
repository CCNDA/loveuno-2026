<?php

namespace App\Http\Controllers;

use App\Models\AlbumPhoto;
use App\Models\Diary;
use App\Models\Verification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// 帳號設定（設計稿 PageMemberSettings）：通知、啟用帳號、字體大小、顯示語言、網路檢測、註銷帳號。
// 帳號管理頁（center.account）上的「帳號設定」表單送到這裡。
class SettingsController extends Controller
{
    public const FONT_SIZES = ['small' => '小', 'medium' => '中', 'large' => '大'];

    public const SCRIPTS = ['hant' => '繁體中文', 'hans' => '简体中文'];

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('settings', [
            'notify_email' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'font_size' => ['required', 'in:' . implode(',', array_keys(self::FONT_SIZES))],
            'script' => ['required', 'in:' . implode(',', array_keys(self::SCRIPTS))],
        ], [], ['font_size' => '字體大小', 'script' => '顯示語言']);

        $user = $request->user();
        $user->forceFill([
            'notify_email' => $request->boolean('notify_email'),
            'is_active' => $request->boolean('is_active'),
            'font_size' => $data['font_size'],
            'script' => $data['script'],
        ])->save();
        $request->session()->put('script', $data['script']); // 這次登入馬上換

        $msg = '設定存好了。';
        if (! $user->is_active) {
            $msg .= '您的帳號已經暫停：其他會員看不到您的檔案和日記，也不能傳紙條、送禮物給您。要恢復，把「啟用帳號」打開就可以。';
        }

        return redirect()->to(route('center.account') . '#settings-title')->with('status', $msg);
    }

    // 網路檢測（設計稿按鈕）：能看到這頁就表示連得到網站；顯示伺服器時間和這次花多久
    public function network(Request $request): View
    {
        $start = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        DB::select('select 1'); // 資料庫也連得到

        return view('center.network', ['ms' => (int) round((microtime(true) - $start) * 1000), 'now' => now()]);
    }

    public function confirmDestroy(): View
    {
        return view('center.delete-account');
    }

    // 註銷帳號：要再輸入一次密碼；帳號、檔案、日記、照片全部刪掉（磁碟上的照片檔也刪），檢舉紀錄留著（管理員還要看）
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('delete', [
            'current_password' => ['required', 'current_password'],
            'confirm' => ['accepted'],
        ], [
            'current_password.current_password' => '密碼不正確。',
            'confirm.accepted' => '請勾選「我了解註銷後不能恢復」。',
        ], ['current_password' => '密碼', 'confirm' => '確認']);

        $user = $request->user();
        $files = array_filter(array_merge(
            [$user->profile?->photo_path, $user->profile?->cover_path],
            AlbumPhoto::where('user_id', $user->id)->pluck('path')->all(),
            Diary::where('user_id', $user->id)->pluck('image_path')->all(),
            Verification::where('user_id', $user->id)->pluck('file_path')->all(),
        ));

        Auth::logout();
        $user->delete(); // 個人檔案、日記、留言、禮物、好友、積點等跟著刪（cascade）
        foreach ($files as $path) {
            VerificationController::deleteQuietly((string) $path);
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', '您的帳號已經註銷了。謝謝您曾經來到愛諾園，願主祝福您。');
    }
}
