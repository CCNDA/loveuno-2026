<?php

namespace App\Support;

use App\Models\Diary;
use App\Models\Friendship;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// 公測：新版每筆寫入記 audit（熊叔叔 11/17 選甲「新版每筆寫入記 audit：誰、哪張表、改前改後」；Asana 1220586286066431），
// 要回寫舊站的那幾種另外排進 legacy_outbox（熊叔叔 11/18 選乙「含回寫」；Asana 1220564106252789）。
// - config loveuno.beta_audit 關著（預設）就什麼都不記；公測站 .env 開
// - 隱藏欄位（密碼、remember_token…）和下面 SECRET 列的欄位一律不記
// - 同步程式從舊站匯入時設 importing=true：照樣記 audit（標 actor=sync），但不排回寫（不然會在兩邊來回打轉）
// - audit 與 outbox 寫在同一個資料庫交易裡（model 事件在交易內觸發時一起回滾）
class BetaChangeRecorder
{
    public static bool $importing = false;

    private const SECRET = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'phone_code'];

    private const SKIP_TABLES = ['beta_audits', 'legacy_outbox', 'sessions', 'jobs', 'cache', 'cache_locks', 'push_subscriptions'];

    // 要回寫舊站的：model => 哪些欄位變了才排（null＝任何欄位）
    private const OUTBOX = [
        Profile::class => null,
        Diary::class => null,
        Friendship::class => null,
        User::class => ['name'],
    ];

    public static function record(string $action, Model $m): void
    {
        if (! config('loveuno.beta_audit') || in_array($m->getTable(), self::SKIP_TABLES, true)) {
            return;
        }
        $hide = array_merge($m->getHidden(), self::SECRET);
        $clean = fn (array $a) => array_diff_key($a, array_flip($hide));
        [$before, $after] = match ($action) {
            'created' => [null, $clean($m->getAttributes())],
            'updated' => [$clean(array_intersect_key($m->getOriginal(), $m->getChanges())), $clean($m->getChanges())],
            'deleted' => [$clean($m->getOriginal()), null],
        };
        if ($action === 'updated' && $after === []) {
            return; // 只改了不記的欄位（例如只換密碼）：記一筆「改了什麼表」就好，不留內容
        }
        $actor = self::$importing ? 'sync' : (auth()->id() !== null ? 'user:' . auth()->id() : (app()->runningInConsole() ? 'console' : 'guest'));
        DB::table('beta_audits')->insert([
            'actor' => $actor,
            'table_name' => $m->getTable(),
            'record_id' => (string) $m->getKey(),
            'action' => $action,
            'before' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'after' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);

        if (self::$importing || ! array_key_exists($m::class, self::OUTBOX)) {
            return;
        }
        $fields = self::OUTBOX[$m::class];
        if ($action === 'updated' && $fields !== null && ! array_intersect($fields, array_keys($m->getChanges()))) {
            return;
        }
        DB::table('legacy_outbox')->insert([
            'model' => class_basename($m),
            'record_id' => (string) $m->getKey(),
            'action' => $action,
            'payload' => json_encode($action === 'deleted' ? $before : $clean($m->getAttributes()), JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
