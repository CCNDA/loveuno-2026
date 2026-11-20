<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

// 公測：把新版的寫入回寫到舊站（熊叔叔 11/18 選乙「含回寫全部做完」、11/20 選丙「照原訂 11/22」；Asana 1220564106252789）。
// 讀 legacy_outbox（BetaChangeRecorder 排的），照下面的對照寫進舊站 basic／profile／diary／relationship。
// - 舊站連線帳號 lu2026_legacy 只有：basic 的 nickname、modify_date 兩欄 UPDATE；profile UPDATE；diary INSERT／UPDATE；relationship INSERT／UPDATE／DELETE
// - 舊表是 utf8mb3：emoji 這類 4 位元組字元先換成「□」（11/18 欄位對照查到的坑）
// - 時間一律 UTC（basic 的 TIMESTAMP 依連線時區換算；11/18 查到的坑）
// - 編號：新版存的 users.legacy_id、diaries.legacy_id；新版自己開的帳號（沒有 legacy_id）不回寫，標 skipped
// - 新日記寫進舊站後，把舊站編號用 query builder 存回新版（不觸發 model 事件 → 不會再排一次，防迴圈）
// - 每筆自己一個嘗試：失敗記 last_error、attempts+1，5 次以後標 failed 不再自動重試；成功標 sent
// - 生日不回寫：新版只有出生年，寫回去會蓋掉舊站的月日
class PushLegacyOutbox extends Command
{
    protected $signature = 'legacy:push-outbox {--limit=200 : 這次最多處理幾筆} {--dry-run : 只列出要做什麼，不寫舊站、不改狀態}';

    protected $description = '公測回寫：把 legacy_outbox 待送的寫進舊站（只印編號與筆數）';

    private const MAX_ATTEMPTS = 5;

    public static function fit(?string $s): string
    {
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '□', (string) $s);
    }

    public function handle(): int
    {
        $legacy = DB::connection('legacy');
        if (! $this->option('dry-run')) {
            $legacy->statement("SET time_zone = '+00:00'");
        }
        $rows = DB::table('legacy_outbox')->where('status', 'pending')->orderBy('id')->limit((int) $this->option('limit'))->get();
        $n = ['sent' => 0, 'skipped' => 0, 'error' => 0];
        foreach ($rows as $r) {
            try {
                $what = $this->apply($r, $legacy, (bool) $this->option('dry-run'));
                if ($this->option('dry-run')) {
                    $this->line("#{$r->id} {$r->model} {$r->action} {$r->record_id} → {$what}");
                    continue;
                }
                $status = str_starts_with($what, 'skip') ? 'skipped' : 'sent';
                DB::table('legacy_outbox')->where('id', $r->id)->update(['status' => $status, 'last_error' => $status === 'skipped' ? $what : null, 'sent_at' => now(), 'updated_at' => now()]);
                $n[$status === 'sent' ? 'sent' : 'skipped']++;
            } catch (Throwable $e) {
                $attempts = $r->attempts + 1;
                DB::table('legacy_outbox')->where('id', $r->id)->update([
                    'attempts' => $attempts,
                    'status' => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending',
                    'last_error' => mb_substr($e->getMessage(), 0, 500),
                    'updated_at' => now(),
                ]);
                $n['error']++;
            }
        }
        $this->info(sprintf('回寫：送出 %d、略過 %d、出錯 %d（共 %d 筆待送）', $n['sent'], $n['skipped'], $n['error'], $rows->count()));

        return $n['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function legacyIdOf(int $userId): ?int
    {
        $id = DB::table('users')->where('id', $userId)->value('legacy_id');

        return $id === null ? null : (int) $id;
    }

    private function apply(object $r, $legacy, bool $dry): string
    {
        $p = json_decode((string) $r->payload, true) ?: [];
        switch ($r->model) {
            case 'User':
                $lid = $this->legacyIdOf((int) $r->record_id);
                if ($lid === null) {
                    return 'skip：新版自己開的帳號，舊站沒有';
                }
                if ($r->action !== 'updated' || ! array_key_exists('name', $p)) {
                    return 'skip：只回寫改暱稱';
                }
                $dry || $legacy->table('basic')->where('id', $lid)->update(['nickname' => self::fit($p['name']), 'modify_date' => now('UTC')->format('Y-m-d H:i:s')]);

                return "basic#{$lid} nickname";

            case 'Profile':
                $lid = $this->legacyIdOf((int) ($p['user_id'] ?? 0));
                if ($lid === null) {
                    return 'skip：新版自己開的帳號，舊站沒有';
                }
                if ($r->action === 'deleted') {
                    return 'skip：個人資料刪除不回寫';
                }
                $set = [];
                if (array_key_exists('gender', $p) && in_array($p['gender'], ['男', '女'], true)) $set['sex'] = $p['gender'];
                foreach (['live_place' => 'live_place', 'church' => 'church', 'job' => 'job'] as $new => $old) {
                    if (array_key_exists($new, $p)) $set[$old] = self::fit($p[$new]);
                }
                if (array_key_exists('church_visible', $p)) $set['church_show'] = $p['church_visible'] ? 1 : 0;
                if (array_key_exists('intro', $p)) $set['intro'] = nl2br(e(self::fit($p['intro'])), false); // 舊站存 <br>
                if ($set === []) {
                    return 'skip：沒有要回寫的欄位';
                }
                $dry || $legacy->table('profile')->where('id', $lid)->update($set);

                return "profile#{$lid} " . implode(',', array_keys($set));

            case 'Diary':
                $owner = $this->legacyIdOf((int) ($p['user_id'] ?? DB::table('diaries')->where('id', $r->record_id)->value('user_id') ?? 0));
                if ($owner === null) {
                    return 'skip：作者是新版自己開的帳號';
                }
                $old = DB::table('diaries')->where('id', $r->record_id)->first(['legacy_source', 'legacy_id']);
                $src = $old->legacy_source ?? ($p['legacy_source'] ?? null);
                if ($src !== null && $src !== 'diary') {
                    return "skip：舊站來源是 {$src}（不是 diary 表），不回寫";
                }
                if ($r->action !== 'deleted' && $old === null) {
                    return 'skip：這篇已經在新版刪掉了';   // 新增還沒送就被刪：不要在舊站留一篇刪不掉的
                }
                $lid = ($old && $old->legacy_source === 'diary') ? (int) $old->legacy_id : (($p['legacy_source'] ?? null) === 'diary' ? (int) $p['legacy_id'] : null);
                $status = ($p['visibility'] ?? 'public') === 'public' ? 0 : -1;
                if ($r->action === 'deleted') {
                    if ($lid === null) {
                        return 'skip：舊站本來就沒有這篇';
                    }
                    $dry || $legacy->table('diary')->where('id', $lid)->update(['status' => -1]);

                    return "diary#{$lid} 隱藏";
                }
                $row = ['ownerid' => $owner, 'subject' => self::fit($p['title'] ?? ''), 'content' => nl2br(e(self::fit($p['body'] ?? '')), false),
                    'date' => substr((string) ($p['published_on'] ?? now('UTC')->toDateString()), 0, 10), 'status' => $status];
                if ($lid !== null) {
                    $dry || $legacy->table('diary')->where('id', $lid)->update($row);

                    return "diary#{$lid} 更新";
                }
                if ($dry) {
                    return 'diary 新增';
                }
                $newId = (int) $legacy->table('diary')->insertGetId($row + ['pic' => '', 'is_r2' => 0]);
                DB::table('diaries')->where('id', $r->record_id)->update(['legacy_source' => 'diary', 'legacy_id' => $newId]); // query builder：不觸發事件
                // 同一篇後面排著的「更新」要用這個編號
                return "diary#{$newId} 新增";

            case 'Friendship':
                $a = $this->legacyIdOf((int) ($p['user_id'] ?? 0));
                $b = $this->legacyIdOf((int) ($p['friend_id'] ?? 0));
                if ($a === null || $b === null) {
                    return 'skip：有一方是新版自己開的帳號';
                }
                $pair = fn ($q) => $q->where(fn ($w) => $w->where('sender', $a)->where('sendee', $b))->orWhere(fn ($w) => $w->where('sender', $b)->where('sendee', $a));
                if ($r->action === 'deleted') {
                    $dry || $legacy->table('relationship')->where($pair)->delete();

                    return "relationship {$a}-{$b} 刪除";
                }
                $level = (int) ($p['level'] ?? 1);
                if ($legacy->table('relationship')->where($pair)->exists()) {
                    $dry || $legacy->table('relationship')->where($pair)->update(['relationship' => $level]);

                    return "relationship {$a}-{$b} 等級 {$level}";
                }
                $dry || $legacy->table('relationship')->insert(['sender' => $a, 'sendee' => $b, 'relationship' => $level]);

                return "relationship {$a}-{$b} 新增";
        }

        return 'skip：不認得的 model';
    }
}
