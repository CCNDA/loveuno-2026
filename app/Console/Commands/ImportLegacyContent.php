<?php

namespace App\Console\Commands;

use App\Support\AvatarImage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

// 匯入 tools/legacy_export_content.php 的輸出（第二階段：日記、日記回覆、代禱、代禱回覆、好友、留言板、相簿、約會、愛的見證）。
// 對照表在 docs/legacy-migration.md；熊叔叔 10/30「3都搬」。Asana 1215470856990770。
// - 會員要先用 legacy:import-export 搬進來（用 users.legacy_id 對）；作者沒搬進來的那筆略過、印筆數
// - 舊站 status -1（後台隱藏）→ 只有作者自己看得到（private，🟠 暫定，等熊叔叔確認）；回覆的 -1 不搬；回覆不截斷（欄位是 text）
// - 舊站「彼此代禱」→ 標成代禱的日記（新版代禱是日記的一種）
// - 用 legacy_source＋legacy_id 對，重跑不會重複、也不會蓋掉會員在新版改過的內容
// - 日記照片重新轉成 JPEG（最長邊 1200，拿掉 EXIF），不用審核（跟新版一樣）
// - 好友：等級一樣（1 普通、2 聊天、3 真實），一對一列；新版已經有這一對就不動（會員在新版解除或升降過，以新版為準）
// - 留言板 → 紙條（10/29「留言板＝傳紙條」）：留言一張（留言者 → 頁主），有回覆再一張（頁主 → 留言者）；都標已讀
// - 相簿：舊站大頭照以外的照片，一人最多 9 張（新版上限），不用審核（10/29「1不用」）
// - 約會：舊站 result 0 等回覆、1／2 準備參加（日期過了新版自己算成「等確認」）、-1 婉拒、3 已參加；
//   -2 有兩種：「超過約會時間未收到答覆」（data/check_if_expire.php 寫的）→ 等回覆（日期過了新版自己算成「超過約會時間」），其他 → 取消
// - 愛的見證：1 公開、0 待審核、-1 不公開
class ImportLegacyContent extends Command
{
    protected $signature = 'legacy:import-content {dir : legacy_export_content.php 的輸出目錄} {--dry-run : 只算筆數}';

    protected $description = '匯入舊站的日記、回覆、代禱、好友、留言板（變紙條）、相簿、約會、愛的見證';

    private array $users = [];

    private array $stats = [];

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        if (! is_file("{$dir}/content_manifest.json")) {
            $this->error('讀不到 content_manifest.json，不像 legacy_export_content.php 的輸出目錄。');

            return self::FAILURE;
        }
        $this->users = DB::table('users')->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();

        // 先日記、代禱，再回覆（回覆要對到上面那一層）
        // 每一種包一個交易：一筆一筆 insert 快很多；中途失敗那一種整個退回，重跑會補（已經有的會跳過）
        foreach ([
            fn () => $this->posts($dir, 'diary', false), fn () => $this->posts($dir, 'pray', true),
            fn () => $this->replies($dir, 'diary_reply', 'diary'), fn () => $this->replies($dir, 'pray_reply', 'pray'),
            fn () => $this->friends($dir), fn () => $this->guestbook($dir), fn () => $this->album($dir),
            fn () => $this->dates($dir), fn () => $this->testimonies($dir),
        ] as $step) {
            DB::transaction($step);
        }

        foreach ($this->stats as $kind => $s) {
            $this->info(sprintf('%s：%s %d、已經有 %d、略過 %d（%s）%s', $kind, $this->option('dry-run') ? '可以匯入' : '匯入',
                $s['new'], $s['exists'], array_sum($s['skip']), $this->skipText($s['skip']),
                ''));
        }

        return self::SUCCESS;
    }

    private function posts(string $dir, string $kind, bool $prayer): void
    {
        $this->start($kind);
        $have = $this->have('diaries', $kind);
        foreach ($this->rows("{$dir}/{$kind}.jsonl") as $r) {
            $id = (int) $r['legacy_id'];
            if (isset($have[$id])) {
                $this->stats[$kind]['exists']++;

                continue;
            }
            $user = $this->users[(int) $r['owner']] ?? null;
            if (! $user) {
                $this->skip($kind, '作者沒搬進來');

                continue;
            }
            $title = trim((string) ($r['title'] ?? ''));
            $body = trim((string) ($r['body'] ?? ''));
            if ($title === '' && $body === '') {
                $this->skip($kind, '沒有內容');

                continue;
            }
            $at = $this->time($r['date'] ?? $r['at'] ?? null) ?? now();
            if ($this->option('dry-run')) {
                $this->stats[$kind]['new']++;

                continue;
            }
            $image = $this->image($dir, (string) ($r['image'] ?? ''));
            $this->insertOrDropFile('diaries', [
                'legacy_source' => $kind, 'legacy_id' => $id, 'user_id' => $user,
                'title' => mb_substr($title !== '' ? $title : mb_substr($body, 0, 30), 0, 150),
                'body' => $body !== '' ? $body : $title,
                'visibility' => (int) ($r['status'] ?? 0) < 0 ? 'private' : 'public',
                'is_prayer' => $prayer,
                'image_path' => $image, 'image_status' => $image ? 'approved' : '', 'image_updated_at' => $image ? now() : null,
                'published_on' => $at->toDateString(), 'created_at' => $at, 'updated_at' => $at,
            ], $image);
            $this->stats[$kind]['new']++;
        }
    }

    private function replies(string $dir, string $kind, string $parentKind): void
    {
        $this->start($kind);
        $parents = DB::table('diaries')->where('legacy_source', $parentKind)->pluck('id', 'legacy_id')->all();
        $have = $this->have('diary_comments', $kind);
        foreach ($this->rows("{$dir}/{$kind}.jsonl") as $r) {
            $id = (int) $r['legacy_id'];
            if (isset($have[$id])) {
                $this->stats[$kind]['exists']++;

                continue;
            }
            if ((int) ($r['status'] ?? 0) < 0) {
                $this->skip($kind, '後台隱藏');

                continue;
            }
            $diary = $parents[(int) $r['parent']] ?? null;
            $user = $this->users[(int) $r['owner']] ?? null;
            $body = trim((string) ($r['body'] ?? ''));
            if (! $diary || ! $user || $body === '') {
                $this->skip($kind, ! $diary ? '日記沒搬進來' : (! $user ? '回覆的人沒搬進來' : '沒有內容'));

                continue;
            }
            if ($this->option('dry-run')) {
                $this->stats[$kind]['new']++;

                continue;
            }
            $at = $this->time($r['at'] ?? null) ?? now();
            DB::table('diary_comments')->insert([
                'legacy_source' => $kind, 'legacy_id' => $id, 'diary_id' => $diary, 'user_id' => $user,
                'body' => $body, 'created_at' => $at, 'updated_at' => $at,
            ]);
            $this->stats[$kind]['new']++;
        }
    }

    private function start(string $kind): void
    {
        $this->stats[$kind] = ['new' => 0, 'exists' => 0, 'skip' => []];
    }

    private function have(string $table, string $source): array
    {
        return DB::table($table)->where('legacy_source', $source)->pluck('legacy_id')->flip()->all();
    }

    private function friends(string $dir): void
    {
        $this->start('friend');
        foreach ($this->rows("{$dir}/friend.jsonl") as $r) {
            $a = $this->users[(int) $r['a']] ?? null;
            $b = $this->users[(int) $r['b']] ?? null;
            $level = (int) $r['level'];
            if (! $a || ! $b || $a === $b || $level < 1 || $level > 3) {
                $this->skip('friend', ! $a || ! $b ? '有一方沒搬進來' : '資料不對');

                continue;
            }
            [$low, $high] = [min($a, $b), max($a, $b)];
            if (DB::table('friendships')->where('user_id', $low)->where('friend_id', $high)->exists()) {
                $this->stats['friend']['exists']++;

                continue;
            }
            if (! $this->option('dry-run')) {
                // 舊站沒有成為好友的日期，用搬家這天（好友週年會從這天算）
                DB::table('friendships')->insert(['user_id' => $low, 'friend_id' => $high, 'level' => $level, 'leveled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->stats['friend']['new']++;
        }
    }

    private function guestbook(string $dir): void
    {
        $this->start('guestbook');
        $have = $this->have('notes', 'guestbook');
        $haveReply = $this->have('notes', 'guestbook_reply');
        $batch = [];
        foreach ($this->rows("{$dir}/guestbook.jsonl") as $r) {
            $id = (int) $r['legacy_id'];
            $from = $this->users[(int) $r['from']] ?? null;
            $to = $this->users[(int) $r['to']] ?? null;
            if (! $from || ! $to || $from === $to) {
                $this->skip('guestbook', $from === $to && $from ? '自己留給自己' : '有一方沒搬進來');

                continue;
            }
            $at = $this->time($r['at'] ?? null) ?? now();
            // 留言：留言者 → 頁主；回覆：頁主 → 留言者
            $parts = [['guestbook', $have, $from, $to, $r['body'] ?? '', $at], ['guestbook_reply', $haveReply, $to, $from, $r['reply'] ?? '', $this->time($r['reply_at'] ?? null) ?? $at]];
            foreach ($parts as [$src, $seen, $sender, $recipient, $body, $when]) {
                $body = trim((string) $body);
                if ($body === '') {
                    continue;
                }
                if (isset($seen[$id])) {
                    $this->stats['guestbook']['exists']++;

                    continue;
                }
                $batch[] = ['legacy_source' => $src, 'legacy_id' => $id, 'sender_id' => $sender, 'recipient_id' => $recipient, 'body' => $body,
                    'read_at' => $when, 'created_at' => $when, 'updated_at' => $when];
                $this->stats['guestbook']['new']++;
            }
            if (count($batch) >= 500) {
                $this->flush('notes', $batch);
            }
        }
        $this->flush('notes', $batch);
    }

    private function album(string $dir): void
    {
        $this->start('album');
        $have = $this->have('album_photos', 'photo');
        $count = DB::table('album_photos')->where('status', '!=', 'rejected')->groupBy('user_id')->selectRaw('user_id, COUNT(*) n')->pluck('n', 'user_id')->all();
        foreach ($this->rows("{$dir}/album.jsonl") as $r) {
            $id = (int) $r['legacy_id'];
            if (isset($have[$id])) {
                $this->stats['album']['exists']++;

                continue;
            }
            $user = $this->users[(int) $r['owner']] ?? null;
            if (! $user) {
                $this->skip('album', '會員沒搬進來');

                continue;
            }
            if (($count[$user] ?? 0) >= \App\Models\AlbumPhoto::MAX) {
                $this->skip('album', '相簿滿了（9 張）');

                continue;
            }
            $rel = (string) ($r['image'] ?? '');
            if (! preg_match('#^images/album_\d+\.img$#', $rel) || ! is_file("{$dir}/{$rel}")) {
                $this->skip('album', '沒有照片檔');

                continue;
            }
            if ($this->option('dry-run')) {
                $this->stats['album']['new']++;
                $count[$user] = ($count[$user] ?? 0) + 1;

                continue;
            }
            $path = $this->image($dir, $rel, 'album/');
            if (! $path) {
                $this->skip('album', '照片讀不出來');

                continue;
            }
            $this->insertOrDropFile('album_photos', ['legacy_source' => 'photo', 'legacy_id' => $id, 'user_id' => $user, 'path' => $path, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()], $path);
            $count[$user] = ($count[$user] ?? 0) + 1;
            $this->stats['album']['new']++;
        }
    }

    private function dates(string $dir): void
    {
        $this->start('date');
        $have = $this->have('meetups', 'private_date');
        foreach ($this->rows("{$dir}/date.jsonl") as $r) {
            $id = (int) $r['legacy_id'];
            if (isset($have[$id])) {
                $this->stats['date']['exists']++;

                continue;
            }
            $from = $this->users[(int) $r['from']] ?? null;
            $to = $this->users[(int) $r['to']] ?? null;
            $on = $this->time($r['date'] ?? null);
            if (! $from || ! $to || ! $on) {
                $this->skip('date', ! $on ? '沒有約會日期' : '有一方沒搬進來');

                continue;
            }
            $msg = trim((string) ($r['result_msg'] ?? ''));
            $expired = (int) $r['result'] === -2 && str_contains($msg, '超過約會時間未收到答覆');
            $status = match ((int) $r['result']) { 1, 2 => 'accepted', -1 => 'declined', -2 => $expired ? 'pending' : 'cancelled', 3 => 'attended', default => 'pending' };
            if ($this->option('dry-run')) {
                $this->stats['date']['new']++;

                continue;
            }
            $at = $this->time($r['at'] ?? null) ?? $on;
            $resultAt = $this->time($r['result_at'] ?? null);
            DB::table('meetups')->insert([
                'legacy_source' => 'private_date', 'legacy_id' => $id, 'sender_id' => $from, 'recipient_id' => $to,
                'meet_on' => $on->toDateString(), 'meet_time' => self::time24((string) ($r['time'] ?? '')),
                'place' => mb_substr(trim((string) ($r['place'] ?? '')) ?: '（舊站沒有填）', 0, 100),
                'activity' => self::activityKey((string) ($r['activity'] ?? '')),
                'company' => str_contains((string) ($r['single'] ?? ''), '單獨') ? 'alone' : 'friends',
                'message' => mb_substr(trim((string) ($r['message'] ?? '')), 0, 1000) ?: null,
                'status' => $status,
                // 接受、婉拒時寫的話放 reply；取消、出席的說明放 closing_note（舊站同一欄）
                'reply' => in_array($status, ['accepted', 'declined'], true) && $msg !== '' ? mb_substr($msg, 0, 500) : null,
                'closing_note' => in_array($status, ['cancelled', 'attended'], true) && $msg !== '' ? mb_substr($msg, 0, 500) : null,
                'responded_at' => $status !== 'pending' ? $resultAt : null,
                'closed_at' => in_array($status, ['cancelled', 'attended'], true) ? $resultAt : null,
                'created_at' => $at, 'updated_at' => $resultAt ?? $at,
            ]);
            $this->stats['date']['new']++;
        }
    }

    private function testimonies(string $dir): void
    {
        $this->start('testi');
        $have = $this->have('love_stories', 'testi');
        foreach ($this->rows("{$dir}/testi.jsonl") as $r) {
            $id = (int) $r['legacy_id'];
            if (isset($have[$id])) {
                $this->stats['testi']['exists']++;

                continue;
            }
            $body = trim((string) ($r['body'] ?? ''));
            if ($body === '') {
                $this->skip('testi', '沒有內容');

                continue;
            }
            $status = match ((int) $r['status']) { 1 => 'approved', 0 => 'pending', default => 'rejected' };
            if ($this->option('dry-run')) {
                $this->stats['testi']['new']++;

                continue;
            }
            // 隱藏的（-1）照片也搬，管理員以後要重新公開時才有照片
            $path = $this->image($dir, (string) ($r['image'] ?? ''), 'love/');
            $at = $this->time($r['at'] ?? null) ?? now();
            $this->insertOrDropFile('love_stories', [
                'legacy_source' => 'testi', 'legacy_id' => $id,
                'submitter_id' => $this->users[(int) $r['owner']] ?? null, 'male_id' => $this->users[(int) $r['male']] ?? null, 'female_id' => $this->users[(int) $r['female']] ?? null,
                'title' => mb_substr(trim((string) ($r['title'] ?? '')) ?: '愛的見證', 0, 50), 'body' => $body, 'photo_path' => $path,
                'status' => $status, 'approved_at' => $status === 'approved' ? $at : null, 'created_at' => $at, 'updated_at' => $at,
            ], $path);
            $this->stats['testi']['new']++;
        }
    }

    // 舊站時間「9:30 AM」→「09:30」（新版每 30 分鐘一格，不是整點／半點的取前一格）
    public static function time24(string $t): string
    {
        if (! preg_match('/^\s*(\d{1,2}):(\d{2})\s*([AP]M)?\s*$/i', $t, $m)) {
            return '10:00';
        }
        $h = (int) $m[1] % 12 + (strtoupper($m[3] ?? '') === 'PM' ? 12 : 0);
        if (! isset($m[3]) || $m[3] === '') {
            $h = (int) $m[1];
        }

        return sprintf('%02d:%02d', min($h, 23), (int) $m[2] >= 30 ? 30 : 0);
    }

    public static function activityKey(string $text): string
    {
        foreach (\App\Models\Meetup::ACTIVITIES as $key => $label) {
            if ($text !== '' && str_contains($text, mb_substr($label, 0, 2))) {
                return $key;
            }
        }

        return 'special'; // 對不到的當「特別聚會」
    }

    private function flush(string $table, array &$batch): void
    {
        if ($batch && ! $this->option('dry-run')) {
            DB::table($table)->insert($batch);
        }
        $batch = [];
    }

    // 一行一筆讀 JSONL，不整包讀進記憶體
    private function rows(string $file): \Generator
    {
        if (! is_file($file)) {
            return;
        }
        $fh = fopen($file, 'r');
        while (($line = fgets($fh)) !== false) {
            $r = json_decode($line, true);
            if (is_array($r) && isset($r['legacy_id'])) {
                yield $r;
            }
        }
        fclose($fh);
    }

    // 匯出檔裡的照片 → 重新轉成 JPEG（最長邊 1200、拿掉 EXIF）放進 avatars 磁碟；不是 images/xxx_數字.img 或讀不出來就 null
    private function image(string $dir, string $rel, string $prefix = ''): ?string
    {
        if (! preg_match('#^images/[a-z]+_\d+\.img$#', $rel) || ! is_file("{$dir}/{$rel}")) {
            return null;
        }
        try {
            $jpeg = AvatarImage::fit("{$dir}/{$rel}", 1200);
        } catch (RuntimeException) {
            return null;
        }
        $path = $prefix . Str::random(40) . '.jpg';
        Storage::disk('avatars')->put($path, $jpeg);

        return $path;
    }

    // 寫資料失敗（例如同時跑兩次撞到唯一索引）就把剛存的照片刪掉，不留沒人用的檔案
    private function insertOrDropFile(string $table, array $row, ?string $path): void
    {
        try {
            DB::table($table)->insert($row);
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('avatars')->delete($path);
            }
            throw $e;
        }
    }

    private function time(?string $t): ?Carbon
    {
        if (! $t) {
            return null;
        }
        try {
            return Carbon::parse($t, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    private function skip(string $kind, string $why): void
    {
        $this->stats[$kind]['skip'][$why] = ($this->stats[$kind]['skip'][$why] ?? 0) + 1;
    }

    private function skipText(array $skip): string
    {
        return $skip ? implode('、', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($skip), $skip)) : '沒有';
    }
}
