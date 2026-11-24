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
// - 時間：舊站 PHP 用 Asia/Taipei 寫 basic.modify_date 字串、連線用伺服器預設時區 → 回寫也寫台北時間字串、不改連線時區
//   （原本連線設 UTC、寫 UTC，回寫的那幾筆比實際早 8 小時，舊站排序／顯示會錯；小布 11/21 審查 🟠3）
// - 編號：新版存的 users.legacy_id、diaries.legacy_id；新版自己開的帳號（沒有 legacy_id）不回寫，標 skipped
// - 新日記寫進舊站後，把舊站編號用 query builder 存回新版（不觸發 model 事件 → 不會再排一次，防迴圈）
// - 每筆自己一個嘗試：失敗記 last_error、attempts+1，5 次以後標 failed 不再自動重試；成功標 sent
// - 生日不回寫：新版只有出生年，寫回去會蓋掉舊站的月日
// - 交友條件（熊叔叔 11/24「交友條件也要寫回」）→ profile.hope_*，只在這次真的改了（payload._changed）才寫，
//   舊的 outbox 沒有 _changed 就不寫。文字欄空白 → 「不拘」（舊站表單的選項）；
//   年齡／身高／體重空白的那一端 → 舊站表單的上下限（舊站配對是 hope_age1 <= 對方年齡 <= hope_age2，上下限＝不限；
//   原本兩端都空寫 0、0，在舊站等於誰都配不到，小布 11/24 審查 c）。匯入時剛好等於上下限的那一端再變回空白（對稱，不來回打轉）
// - 照順序：同一筆（model＋record_id）前面還有沒送成功的（pending／failed），後面的這一輪先不送，
//   不然下一輪重送舊的那筆會把新內容蓋回去（小布 11/21 審查 🟠3）；failed 的要人處理後才會放行
// - 日記新增重送時先找舊站有沒有一模一樣的一篇（上次 insert 成功、存回編號之前出錯），有就沿用（🟠4）
class PushLegacyOutbox extends Command
{
    protected $signature = 'legacy:push-outbox {--limit=200 : 這次最多處理幾筆} {--dry-run : 只列出要做什麼，不寫舊站、不改狀態}';

    protected $description = '公測回寫：把 legacy_outbox 待送的寫進舊站（只印編號與筆數）';

    private const MAX_ATTEMPTS = 5;

    public static function fit(?string $s): string
    {
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '□', (string) $s);
    }

    // 舊站表單的範圍（member/profile2.php：年齡 18～99、身高 90～220、體重 30～200）；ImportLegacyExport 也用
    public const HOPE_RANGE = ['age' => [18, 99], 'height' => [90, 220], 'weight' => [30, 200]];

    // 新版交友條件 → 舊站 profile.hope_*；只回傳這次有改的那幾組
    public static function hopeFields(array $p): array
    {
        $changed = $p['_changed'] ?? null;
        if (! is_array($changed)) {
            return [];
        }
        $set = [];
        foreach (['pref_gender' => 'hope_sex', 'pref_marital' => 'hope_marriage', 'pref_faith' => 'hope_religion', 'pref_place' => 'hope_live_place'] as $new => $old) {
            if (in_array($new, $changed, true)) {
                $v = trim((string) ($p[$new] ?? ''));
                $set[$old] = $v === '' ? '不拘' : mb_substr(self::fit($v), 0, 20);
            }
        }
        foreach (self::HOPE_RANGE as $k => [$lo, $hi]) {
            if (! array_intersect(["pref_{$k}_min", "pref_{$k}_max"], $changed)) {
                continue;
            }
            $a = is_numeric($p["pref_{$k}_min"] ?? null) ? (int) $p["pref_{$k}_min"] : null;
            $b = is_numeric($p["pref_{$k}_max"] ?? null) ? (int) $p["pref_{$k}_max"] : null;
            [$set["hope_{$k}1"], $set["hope_{$k}2"]] = [$a ?? $lo, $b ?? $hi];
        }

        return $set;
    }

    public function handle(): int
    {
        $legacy = DB::connection('legacy');
        $rows = DB::table('legacy_outbox')->where('status', 'pending')->orderBy('id')->limit((int) $this->option('limit'))->get();
        $n = ['sent' => 0, 'skipped' => 0, 'error' => 0, 'held' => 0];
        foreach ($rows as $r) {
            $earlier = DB::table('legacy_outbox')->where('model', $r->model)->where('record_id', $r->record_id)
                ->where('id', '<', $r->id)->whereIn('status', ['pending', 'failed'])->exists();
            if ($earlier && ! $this->option('dry-run')) {
                $n['held']++;
                continue;
            }
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
        $this->info(sprintf('回寫：送出 %d、略過 %d、出錯 %d、等前一筆 %d（共 %d 筆待送）', $n['sent'], $n['skipped'], $n['error'], $n['held'], $rows->count()));

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
                $dry || $legacy->table('basic')->where('id', $lid)->update(['nickname' => self::fit($p['name']), 'modify_date' => now('Asia/Taipei')->format('Y-m-d H:i:s')]);

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
                $set += self::hopeFields($p);
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
                    'date' => substr((string) ($p['published_on'] ?? now('Asia/Taipei')->toDateString()), 0, 10), 'status' => $status];
                if ($lid !== null) {
                    $dry || $legacy->table('diary')->where('id', $lid)->update($row);

                    return "diary#{$lid} 更新";
                }
                if ($dry) {
                    return 'diary 新增';
                }
                $dup = $r->attempts > 0
                    ? $legacy->table('diary')->where('ownerid', $owner)->where('subject', $row['subject'])->where('date', $row['date'])->where('content', $row['content'])->orderByDesc('id')->value('id')
                    : null;
                $newId = $dup !== null ? (int) $dup : (int) $legacy->table('diary')->insertGetId($row + ['pic' => '', 'is_r2' => 0]);
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
