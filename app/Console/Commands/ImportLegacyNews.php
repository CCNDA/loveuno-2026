<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

// 搬舊站的站務公告（熊叔叔 10/29「6搬」）。來源是舊站根目錄的 news.csv（id、timestamp、content、status），
// 複製一份放在 database/data/legacy_news.csv。
// - 舊站首頁只列 status = 1（right-main.php），所以 status 1 照原本的時間發佈，status 0 存成草稿（管理員可以再發佈）
// - 舊站沒有標題：用內容的第一句（30 字內）
// - 內容是 HTML：<br> 換成換行，<a> 只留文字＋網址（只留 http／https），其他標籤（圖片）拿掉
// - 用 legacy_id 對，重跑不會重複；已經匯入的不蓋掉（管理員可能改過）
class ImportLegacyNews extends Command
{
    protected $signature = 'legacy:import-news {csv? : news.csv 的路徑} {--dry-run : 只印筆數}';

    protected $description = '匯入舊站的站務公告（news.csv）';

    public function handle(): int
    {
        $path = $this->argument('csv') ?? database_path('data/legacy_news.csv');
        $fh = @fopen($path, 'r');
        if (! $fh) {
            $this->error('讀不到 ' . $path);

            return self::FAILURE;
        }
        $head = fgetcsv($fh, escape: '');
        if ($head !== ['id', 'timestamp', 'content', 'status']) {
            $this->error('欄位不是 id、timestamp、content、status，停止。');

            return self::FAILURE;
        }
        $new = $skip = 0;
        while (($row = fgetcsv($fh, escape: '')) !== false) {
            if (count($row) !== 4 || ! ctype_digit($row[0])) {
                continue;
            }
            [$id, $ts, $html, $status] = $row;
            if (Announcement::where('legacy_id', (int) $id)->exists()) {
                $skip++;

                continue;
            }
            $body = self::toText($html);
            try {
                $at = Carbon::createFromFormat('Y/n/j G:i:s', $ts, config('app.timezone'));
            } catch (\Throwable) {
                $this->warn("第 {$id} 則的時間讀不懂（{$ts}），略過");

                continue;
            }
            if (! $this->option('dry-run')) {
                $a = new Announcement;
                $a->forceFill([
                    'legacy_id' => (int) $id,
                    'title' => self::titleOf($body),
                    'body' => $body,
                    'published_at' => $status === '1' ? $at : null,
                    'created_at' => $at,
                    'updated_at' => now(),
                ])->save();
            }
            $new++;
        }
        fclose($fh);
        $this->info(sprintf('%s %d 則，已經有的 %d 則沒動', $this->option('dry-run') ? '可以匯入' : '匯入', $new, $skip));

        return self::SUCCESS;
    }

    public static function toText(string $html): string
    {
        $s = preg_replace('#<(script|style)\b.*?</\1\s*>#is', '', $html); // 這兩種標籤裡面的字也不要
        $s = preg_replace('#<br\s*/?>#i', "\n", $s);
        $s = preg_replace_callback('#<a\s[^>]*href\s*=\s*["\']?([^"\'\s>]+)["\']?[^>]*>(.*?)</a>#is', function ($m) {
            $text = trim(strip_tags($m[2]));
            $url = html_entity_decode($m[1]);
            $ok = preg_match('#^https?://#i', $url);

            return $text === '' ? ($ok ? $url : '') : ($ok ? $text . '（' . $url . '）' : $text);
        }, $s);
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace("/[ \t]+\n/", "\n", $s);

        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $s)));
    }

    public static function titleOf(string $body): string
    {
        $first = preg_split('/(?<=[。！？!?])|\n/u', $body, 2)[0];
        $first = trim($first);

        return mb_strlen($first) > 30 ? mb_substr($first, 0, 30) . '…' : $first;
    }
}
