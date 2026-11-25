<?php

namespace App\Support;

use App\Models\AlbumPhoto;
use App\Models\Diary;
use App\Models\Feedback;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

// 註銷帳號：清掉個資、照片、日記，帳號那一列和會員編號留著（紙條、禮物、檢舉紀錄要對得上人）。
// 2026-11-26 小芯（Asana 1220690700083353）：從 SettingsController::destroy 搬過來，會員自己註銷、後台「代為註銷」共用；
// account_deletions 多記 deleted_by（自己註銷＝本人，代為註銷＝那位管理員）。熊叔叔 11/19 TG：舊站刪除要保留會員 ID、記刪除時間，新版也要。
class AccountDeleter
{
    /**
     * @param  User  $by  執行的人（本人或管理員）
     * @param  callable|null  $also  要跟清除放在同一個交易裡的事（例如寫 admin_actions）
     * @return bool  false＝這個帳號已經註銷過了，什麼都沒做
     */
    public static function delete(User $user, string $reason, User $by, ?callable $also = null): bool
    {
        $files = array_filter(array_merge(
            [$user->profile?->photo_path, $user->profile?->cover_path],
            AlbumPhoto::where('user_id', $user->id)->pluck('path')->all(),
            Diary::where('user_id', $user->id)->pluck('image_path')->all(),
            Verification::where('user_id', $user->id)->pluck('file_path')->all(),
            Feedback::where('user_id', $user->id)->pluck('screenshot_path')->all(), // 回報的截圖可能有別人的資料
        ));

        $done = DB::transaction(function () use ($user, $reason, $by, $also) {
            // 先鎖住這一列再看是不是已經註銷：兩個人同時按（或連按兩次）不會清兩次、留兩筆紀錄
            if (User::whereKey($user->id)->lockForUpdate()->value('deleted_at') !== null) {
                return false;
            }
            DB::table('account_deletions')->insert(['user_id' => $user->id, 'deleted_by' => $by->id, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
            Diary::where('user_id', $user->id)->delete();         // 留言、禱告跟著刪
            DB::table('diary_comments')->where('user_id', $user->id)->delete(); // 在別人日記下的留言
            DB::table('diary_prayers')->where('user_id', $user->id)->delete();
            AlbumPhoto::where('user_id', $user->id)->delete();
            Verification::where('user_id', $user->id)->delete();
            DB::table('friendships')->where('user_id', $user->id)->orWhere('friend_id', $user->id)->delete();
            DB::table('meetups')->where('sender_id', $user->id)->orWhere('recipient_id', $user->id)->delete(); // 舊站 delete_profile_final.php 也刪
            DB::table('point_transactions')->where('user_id', $user->id)->delete();
            Feedback::where('user_id', $user->id)->delete(); // 回報問題／建議（user_id 是 nullOnDelete，帳號列沒刪所以要自己刪）
            $user->profile()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete(); // 2026-11-26 小芯（小布審查）：所有裝置的登入一起失效
            $user->forceFill([
                'name' => '已註銷會員',
                'email' => 'deleted-' . $user->id . '@deleted.invalid',
                'email_pending' => null,
                'password' => Str::random(64),
                'remember_token' => Str::random(60),
                'phone' => null, 'phone_pending' => null, 'phone_code_hash' => null,
                'is_active' => false, 'notify_email' => false, 'points' => 0, 'is_admin' => false, 'is_super_admin' => false,
                'deleted_at' => now(),
            ])->save();
            if ($also) {
                $also();
            }

            return true;
        });
        if (! $done) {
            return false;
        }
        foreach ($files as $path) {
            \App\Http\Controllers\VerificationController::deleteQuietly((string) $path);
        }
        // 讓管理員知道（舊站會寄信給管理員）
        Notification::route('mail', config('loveuno.contact_email'))->notify(new \App\Notifications\AccountDeleted($user->id, $reason, $by->is($user) ? null : $by->id));

        return true;
    }
}
