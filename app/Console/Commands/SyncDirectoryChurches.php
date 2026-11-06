<?php

namespace App\Console\Commands;

use App\Models\DirectoryChurch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// 每天把名錄公開的 church.json（https://church.oursweb.net/data/church.json，約 13MB、兩萬筆機構）抓下來，
// 只留「教會」（og＝教會，約一萬四千筆），存進 directory_churches。熊叔叔 11/6 決定 #11（甲）。
// 名錄掛掉或回來的筆數太少（例如半份檔、維修頁）：整批不動，不刪舊資料（--min，預設 5000 筆）。
class SyncDirectoryChurches extends Command
{
    protected $signature = 'directory:sync-churches {--file= : 用本機的 church.json（測試、名錄連不到時）} {--min=5000 : 少於這麼多筆教會就不更新}';

    protected $description = '從名錄的 church.json 同步教會清單（自我介紹選教會用）';

    public function handle(): int
    {
        $file = (string) $this->option('file');
        if ($file !== '') {
            $raw = @file_get_contents($file);
        } else {
            $url = (string) config('loveuno.directory_churches_url');
            $res = Http::timeout(120)->get($url);
            if (! $res->successful()) {
                $this->error("名錄回 HTTP {$res->status()}，這次不更新：{$url}");

                return self::FAILURE;
            }
            $raw = $res->body();
        }
        $all = json_decode((string) $raw, true);
        if (! is_array($all)) {
            $this->error('church.json 讀不懂（不是 JSON），這次不更新');

            return self::FAILURE;
        }

        $now = now();
        $rows = [];
        foreach ($all as $oid => $o) {
            if (! is_array($o) || ($o['og'] ?? '') !== '教會' || ! ctype_digit((string) $oid)) {
                continue;
            }
            $name = trim((string) ($o['oc'] ?? '')) ?: trim((string) ($o['on'] ?? ''));
            if ($name === '') {
                continue;
            }
            $alias = trim((string) ($o['on'] ?? ''));
            $rows[] = [
                'oid' => (int) $oid,
                'name' => mb_substr($name, 0, 150),
                'alias' => $alias !== $name ? mb_substr($alias, 0, 150) : '',
                'country_code' => strtoupper(substr((string) ($o['cc'] ?? ''), 0, 2)),
                'country' => mb_substr((string) ($o['co'] ?? ''), 0, 30),
                'city' => mb_substr((string) ($o['cy'] ?? ''), 0, 30),
                'district' => mb_substr((string) ($o['tn'] ?? ''), 0, 30),
                'synced_at' => $now,
            ];
        }
        $min = (int) $this->option('min');
        if (count($rows) < $min) {
            $this->error(sprintf('只讀到 %d 筆教會（少於 %d），可能名錄出問題，這次不更新', count($rows), $min));

            return self::FAILURE;
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DirectoryChurch::upsert($chunk, ['oid'], ['name', 'alias', 'country_code', 'country', 'city', 'district', 'synced_at']);
        }
        // 名錄已經沒有的（刪除、改成不是教會）：拿掉。會員檔案裡存的 church_oid、教會名稱不動
        $gone = DirectoryChurch::where('synced_at', '<', $now)->orWhereNull('synced_at')->delete();
        Cache::forget('directory_countries');
        $this->info(sprintf('教會 %d 筆（拿掉 %d 筆名錄已經沒有的）', count($rows), $gone));

        return self::SUCCESS;
    }
}
