<?php

namespace App\Http\Controllers;

use App\Models\AlbumPhoto;
use App\Models\Diary;
use App\Models\Verification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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

    // 註銷帳號：要再輸入一次密碼、寫原因（舊站 delete_file.php 也要寫原因）。
    // 不整列刪掉：別人收到的紙條、禮物是騷擾的證據，檢舉也要看得出是誰（兩面審查 10/29）。
    // 所以是「清掉個資」：暱稱、Email、密碼、手機、個人檔案、照片、日記、相簿、認證、好友、積點都刪，帳號列標成已註銷。
    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('delete', [
            'current_password' => ['required', 'current_password'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm' => ['accepted'],
        ], [
            'current_password.current_password' => '密碼不正確。',
            'reason.min' => '請寫一下原因，至少 10 個字（讓我們知道可以怎麼改進）。',
            'confirm.accepted' => '請勾選「我了解註銷後不能恢復」。',
        ], ['current_password' => '密碼', 'reason' => '原因', 'confirm' => '確認']);

        $user = $request->user();
        $files = array_filter(array_merge(
            [$user->profile?->photo_path, $user->profile?->cover_path],
            AlbumPhoto::where('user_id', $user->id)->pluck('path')->all(),
            Diary::where('user_id', $user->id)->pluck('image_path')->all(),
            Verification::where('user_id', $user->id)->pluck('file_path')->all(),
        ));

        DB::transaction(function () use ($user, $data) {
            DB::table('account_deletions')->insert(['user_id' => $user->id, 'reason' => trim($data['reason']), 'created_at' => now(), 'updated_at' => now()]);
            Diary::where('user_id', $user->id)->delete();         // 留言、禱告跟著刪
            DB::table('diary_comments')->where('user_id', $user->id)->delete(); // 在別人日記下的留言
            DB::table('diary_prayers')->where('user_id', $user->id)->delete();
            AlbumPhoto::where('user_id', $user->id)->delete();
            Verification::where('user_id', $user->id)->delete();
            DB::table('friendships')->where('user_id', $user->id)->orWhere('friend_id', $user->id)->delete();
            DB::table('point_transactions')->where('user_id', $user->id)->delete();
            $user->profile()->delete();
            $user->forceFill([
                'name' => '已註銷會員',
                'email' => 'deleted-' . $user->id . '@deleted.invalid',
                'email_pending' => null,
                'password' => Str::random(64),
                'remember_token' => Str::random(60),
                'phone' => null, 'phone_pending' => null, 'phone_code_hash' => null,
                'is_active' => false, 'notify_email' => false, 'points' => 0, 'is_admin' => false,
                'deleted_at' => now(),
            ])->save();
        });
        foreach ($files as $path) {
            VerificationController::deleteQuietly((string) $path);
        }
        // 讓管理員知道（舊站會寄信給管理員）
        Notification::route('mail', config('loveuno.contact_email'))->notify(new \App\Notifications\AccountDeleted($user->id, trim($data['reason'])));

        Auth::logout(); // 資料都處理好了才登出
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', '您的帳號已經註銷了。謝謝您曾經來到愛諾園，願主祝福您。');
    }
}
