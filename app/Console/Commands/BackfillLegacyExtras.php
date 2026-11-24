<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// 補搬舊站的註冊日期與認證狀態（熊叔叔 2026-11-24 TG：新舊資料庫比對後選「甲 兩個都補」；Asana 1220841267386727）
//
// - 註冊日期：舊站 basic.join_date → users.created_at。舊站 PHP 用台北時間寫字串、連線 session 是 UTC，
//   讀出來的字串就是台北牆上時間（11/24 查：註冊高峰 22～23 時），新版 app.timezone 也是台北 → 原字串直接寫
// - 認證：舊站 certification 的 company／id／church 是 3（已通過）的 → verifications（job／id／church，approved）。
//   只搬狀態、時間、公司名稱／網址／Email；證件圖不搬（file_path 空字串）。新版已經有同一種認證的（會員在新版重新申請過）不動
// - 用 query builder 寫：不觸發 model 事件，不會排回寫舊站
// - 可以重複跑（公測期間每天 full 同步跑一次，舊站新通過的認證也會補上）
class BackfillLegacyExtras extends Command
{
    protected $signature = 'legacy:backfill-extras {--dry-run : 只算筆數，不寫}';

    protected $description = '補搬舊站的註冊日期（join_date）與職業／身分／教會認證狀態';

    private const KINDS = [
        'job' => ['company_certification', 'company_timestamp'],
        'id' => ['id_certification', 'id_timestamp'],
        'church' => ['church_certification', 'church_timestamp'],
    ];

    public function handle(): int
    {
        $legacy = DB::connection('legacy');
        $dry = (bool) $this->option('dry-run');
        $map = DB::table('users')->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();   // 舊站編號 → 新版 id

        $dates = 0;
        $current = DB::table('users')->whereNotNull('legacy_id')->pluck('created_at', 'id')->all();   // 新版 id → 目前的 created_at
        $legacy->table('basic')->select(['id', 'join_date'])->orderBy('id')->chunk(2000, function ($rows) use ($map, $current, $dry, &$dates) {
            foreach ($rows as $r) {
                $uid = $map[(int) $r->id] ?? null;
                $jd = (string) $r->join_date;
                if ($uid === null || ! preg_match('/^(199\d|20\d\d)-\d\d-\d\d \d\d:\d\d:\d\d$/', $jd) || (string) ($current[$uid] ?? '') === $jd) {
                    continue;   // 新版沒有這位、舊站日期是 0000-00-00 這類壞值、或已經一樣
                }
                $dry || DB::table('users')->where('id', $uid)->update(['created_at' => $jd]);
                $dates++;
            }
        });

        $have = DB::table('verifications')->select(['user_id', 'kind'])->get()->map(fn ($v) => $v->user_id . ':' . $v->kind)->flip()->all();
        $added = array_fill_keys(array_keys(self::KINDS), 0);
        $cols = ['id', 'company_name', 'company_url', 'company_email'];
        foreach (self::KINDS as [$st, $ts]) {
            $cols[] = $st;
            $cols[] = $ts;
        }
        $legacy->table('certification')->select($cols)
            ->where(fn ($w) => $w->where('company_certification', 3)->orWhere('id_certification', 3)->orWhere('church_certification', 3))
            ->orderBy('id')->chunk(2000, function ($rows) use ($map, $dry, &$have, &$added) {
                $insert = [];
                foreach ($rows as $r) {
                    $uid = $map[(int) $r->id] ?? null;
                    if ($uid === null) {
                        continue;
                    }
                    foreach (self::KINDS as $kind => [$st, $ts]) {
                        if ((int) $r->{$st} !== 3 || isset($have["{$uid}:{$kind}"])) {
                            continue;
                        }
                        $at = preg_match('/^(19|20)\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/', (string) $r->{$ts}) ? (string) $r->{$ts} : now()->format('Y-m-d H:i:s');
                        $insert[] = [
                            'user_id' => $uid, 'kind' => $kind, 'status' => 'approved',
                            'company' => $kind === 'job' ? mb_substr(trim((string) $r->company_name), 0, 50) : '',
                            'company_url' => $kind === 'job' ? mb_substr(trim((string) $r->company_url), 0, 200) : '',
                            'company_email' => $kind === 'job' ? mb_substr(trim((string) $r->company_email), 0, 100) : '',
                            'file_path' => '', 'reject_reason' => '', 'reviewed_by' => null,
                            'reviewed_at' => $at, 'created_at' => $at, 'updated_at' => now(),
                        ];
                        $have["{$uid}:{$kind}"] = true;
                        $added[$kind]++;
                    }
                }
                if ($insert && ! $dry) {
                    foreach (array_chunk($insert, 500) as $part) {
                        DB::table('verifications')->insert($part);
                    }
                }
            });

        $this->info(sprintf('%s註冊日期 %d 位；認證 職業 %d、身分 %d、教會 %d', $dry ? '（試跑）要改 ' : '已改 ', $dates, $added['job'], $added['id'], $added['church']));

        return self::SUCCESS;
    }
}
