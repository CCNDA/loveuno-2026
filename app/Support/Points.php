<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// 愛諾積點：給點、花點都走這裡，一筆紀錄（point_transactions）配一次餘額（users.points）更新，放在同一個交易裡。
// 點數照設計稿 PageMemberPoints/PointAward.tsx；設計稿沒有的（寫日記）照舊站 member/write_diary.php。
class Points
{
    // 任務獎勵（一次性，ref = once）
    public const TASKS = [
        'profile' => [20, '完成基本資料建檔'],
        'preferences' => [10, '完成希望對象建檔'],
        'love' => [10, '完成戀愛履歷建檔'],
        'photo' => [20, '完成主顯示照片建檔並通過審核'],
        // Figma「可獲得積點項目」有這四項（沒寫點數），點數照舊站：手機、職業、身分、教會各 +10
        'phone' => [10, '完成手機認證'],
        'job' => [10, '完成職業E-mail認證'],
        'id' => [10, '完成身分文件認證'],
        'church' => [10, '完成教會週報認證'],
    ];

    // 每天一次（ref = 日期）
    public const DAILY = [
        'daily_login' => [1, '每日登入'],
        'diary' => [3, '寫日記'],
    ];

    // 花點（設計稿「可花費積點項目」）
    public const COSTS = [
        'gift' => [3, '送禮物給好友'],
        'date' => [10, '邀請約會見面'],
    ];

    // 給點；同一個 type＋ref 已經給過就不再給。回傳這次有沒有給
    public static function award(User $user, string $type, string $ref, int $amount, string $label): bool
    {
        try {
            DB::transaction(function () use ($user, $type, $ref, $amount, $label) {
                DB::table('point_transactions')->insert([
                    'user_id' => $user->id, 'type' => $type, 'ref' => $ref, 'amount' => $amount, 'label' => $label,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('users')->where('id', $user->id)->increment('points', $amount);
            });
        } catch (UniqueConstraintViolationException) {
            return false; // 已經給過了
        }
        $user->points = (int) DB::table('users')->where('id', $user->id)->value('points');

        return true;
    }

    public static function daily(User $user, string $type): bool
    {
        [$amount, $label] = self::DAILY[$type];

        return self::award($user, $type, now()->toDateString(), $amount, $label);
    }

    // 花點；不夠就丟 NotEnoughPoints（不扣）。$ref 要每次不一樣（例如禮物編號）
    public static function spend(User $user, string $type, string $ref, ?string $label = null): void
    {
        [$amount, $default] = self::COSTS[$type];
        DB::transaction(function () use ($user, $type, $ref, $amount, $label, $default) {
            $balance = (int) DB::table('users')->where('id', $user->id)->lockForUpdate()->value('points');
            if ($balance < $amount) {
                throw new NotEnoughPoints('積點不夠');
            }
            DB::table('point_transactions')->insert([
                'user_id' => $user->id, 'type' => $type, 'ref' => $ref, 'amount' => -$amount, 'label' => $label ?? $default,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('users')->where('id', $user->id)->decrement('points', $amount);
            $user->points = $balance - $amount;
        });
    }

    // 管理員加減點（上線前檢測表 D-05 簡單版：單一會員＋原因）。會員的積點紀錄會看到「管理員調整：原因」。
    // 扣到變負的就丟 NotEnoughPoints（不扣）。ref 每次不一樣，不會被「同一筆不重複給」擋掉
    public static function adjust(User $user, int $amount, string $reason): void
    {
        DB::transaction(function () use ($user, $amount, $reason) {
            $balance = (int) DB::table('users')->where('id', $user->id)->lockForUpdate()->value('points');
            if ($balance + $amount < 0) {
                throw new NotEnoughPoints('積點不夠扣');
            }
            DB::table('point_transactions')->insert([
                'user_id' => $user->id, 'type' => 'admin', 'ref' => 'admin-' . now()->format('YmdHisu') . '-' . bin2hex(random_bytes(3)),
                'amount' => $amount, 'label' => '管理員調整：' . $reason,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('users')->where('id', $user->id)->increment('points', $amount);
            $user->points = $balance + $amount;
        });
    }

    // 檢查四個任務，做到的就給點（重複呼叫沒關係）
    public static function checkTasks(User $user): void
    {
        $p = $user->profile()->first();
        if (! $p) {
            return;
        }
        $done = [
            'profile' => trim((string) $p->live_place) !== '' && trim((string) $p->job) !== ''
                && collect(['faith', 'marital', 'children', 'education', 'blood_type', 'smoking', 'drinking'])->every(fn ($k) => (string) $p->{$k} !== ''),
            // 任何一項交友條件設了就算（11/5 補上婚姻狀態、信仰、身高、體重時這裡漏改；小布 11/13 檢測表 2-03）
            'preferences' => $p->hasExplicitPreferences(),
            // 0.16～0.20 暫時用「寫了自我介紹」給 type=intro；0.21.0 起改成問卷、type=love，舊的 intro 紀錄不算完成
            'love' => LoveHistory::isComplete($p->love_history),
            'photo' => $p->photo_status === 'approved',
            'phone' => $user->phone_verified_at !== null,
        ];
        $approved = \App\Models\Verification::where('user_id', $user->id)->where('status', 'approved')->pluck('kind')->all();
        foreach (['job', 'id', 'church'] as $k) {
            $done[$k] = in_array($k, $approved, true);
        }
        foreach ($done as $type => $ok) {
            if ($ok) {
                [$amount, $label] = self::TASKS[$type];
                self::award($user, $type, 'once', $amount, $label);
            }
        }
    }

    // 任務做了沒、哪天做的：[type => created_at|null]
    public static function taskStatus(User $user): array
    {
        $rows = DB::table('point_transactions')->where('user_id', $user->id)->where('ref', 'once')
            ->whereIn('type', array_keys(self::TASKS))->pluck('created_at', 'type');

        return collect(self::TASKS)->mapWithKeys(fn ($v, $type) => [$type => $rows[$type] ?? null])->all();
    }
}
