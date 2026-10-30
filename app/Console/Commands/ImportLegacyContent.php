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

// 匯入 tools/legacy_export_content.php 的輸出（第二階段：日記、日記回覆、代禱、代禱回覆）。
// 對照表在 docs/legacy-migration.md；熊叔叔 10/30「3都搬」。Asana 1215470856990770。
// - 會員要先用 legacy:import-export 搬進來（用 users.legacy_id 對）；作者沒搬進來的那筆略過、印筆數
// - 舊站 status -1（後台隱藏）→ 只有作者自己看得到（private）；回覆的 -1 不搬
// - 舊站「彼此代禱」→ 標成代禱的日記（新版代禱是日記的一種）
// - 用 legacy_source＋legacy_id 對，重跑不會重複、也不會蓋掉會員在新版改過的內容
// - 日記照片重新轉成 JPEG（最長邊 1200，拿掉 EXIF），不用審核（跟新版一樣）
class ImportLegacyContent extends Command
{
    protected $signature = 'legacy:import-content {dir : legacy_export_content.php 的輸出目錄} {--dry-run : 只算筆數}';

    protected $description = '匯入舊站的日記、日記回覆、代禱、代禱回覆';

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
        $this->posts($dir, 'diary', false);
        $this->posts($dir, 'pray', true);
        $this->replies($dir, 'diary_reply', 'diary');
        $this->replies($dir, 'pray_reply', 'pray');

        foreach ($this->stats as $kind => $s) {
            $this->info(sprintf('%s：%s %d、已經有 %d、略過 %d（%s）%s', $kind, $this->option('dry-run') ? '可以匯入' : '匯入',
                $s['new'], $s['exists'], array_sum($s['skip']), $this->skipText($s['skip']),
                empty($s['truncated']) ? '' : sprintf('；超過 500 字截斷 %d', $s['truncated'])));
        }

        return self::SUCCESS;
    }

    private function posts(string $dir, string $kind, bool $prayer): void
    {
        $this->stats[$kind] = ['new' => 0, 'exists' => 0, 'skip' => []];
        $have = DB::table('diaries')->where('legacy_source', $kind)->pluck('legacy_id')->flip()->all();
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
            $image = $this->image($dir, $r['image'] ?? null);
            DB::table('diaries')->insert([
                'legacy_source' => $kind, 'legacy_id' => $id, 'user_id' => $user,
                'title' => mb_substr($title !== '' ? $title : mb_substr($body, 0, 30), 0, 150),
                'body' => $body !== '' ? $body : $title,
                'visibility' => (int) ($r['status'] ?? 0) < 0 ? 'private' : 'public',
                'is_prayer' => $prayer,
                'image_path' => $image, 'image_status' => $image ? 'approved' : '', 'image_updated_at' => $image ? now() : null,
                'published_on' => $at->toDateString(), 'created_at' => $at, 'updated_at' => $at,
            ]);
            $this->stats[$kind]['new']++;
        }
    }

    private function replies(string $dir, string $kind, string $parentKind): void
    {
        $this->stats[$kind] = ['new' => 0, 'exists' => 0, 'skip' => []];
        $parents = DB::table('diaries')->where('legacy_source', $parentKind)->pluck('id', 'legacy_id')->all();
        $have = DB::table('diary_comments')->where('legacy_source', $kind)->pluck('legacy_id')->flip()->all();
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
            if (mb_strlen($body) > 500) {
                $this->stats[$kind]['truncated'] = ($this->stats[$kind]['truncated'] ?? 0) + 1; // 新版留言 500 字
            }
            if ($this->option('dry-run')) {
                $this->stats[$kind]['new']++;

                continue;
            }
            $at = $this->time($r['at'] ?? null) ?? now();
            DB::table('diary_comments')->insert([
                'legacy_source' => $kind, 'legacy_id' => $id, 'diary_id' => $diary, 'user_id' => $user,
                'body' => mb_substr($body, 0, 500), 'created_at' => $at, 'updated_at' => $at,
            ]);
            $this->stats[$kind]['new']++;
        }
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

    private function image(string $dir, ?string $rel): ?string
    {
        if (! $rel || ! preg_match('#^images/diary_\d+\.img$#', $rel) || ! is_file("{$dir}/{$rel}")) {
            return null;
        }
        try {
            $jpeg = AvatarImage::fit("{$dir}/{$rel}", 1200);
        } catch (RuntimeException) {
            return null;
        }
        $path = Str::random(40) . '.jpg';
        Storage::disk('avatars')->put($path, $jpeg);

        return $path;
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
