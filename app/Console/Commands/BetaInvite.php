<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

// 公測邀請名單（熊叔叔 11/16 選乙；Asana 1220564012971609）。不印 email、密碼，只印 id。
//   php artisan beta:invite --list                    名單上有誰（id、舊站編號、邀請時間）
//   php artisan beta:invite --id=12 --id=34           用新版 id 加進名單
//   php artisan beta:invite --legacy=1012345          用舊站會員編號加進名單
//   php artisan beta:invite --file=/path/ids.txt      一行一個：數字＝新版 id；L 開頭＝舊站編號（例 L1012345）
//   以上加 --revoke 就是從名單拿掉；加 --dry-run 只看不改
class BetaInvite extends Command
{
    protected $signature = 'beta:invite {--id=* : 新版會員 id} {--legacy=* : 舊站會員編號} {--file= : 名單檔}
        {--revoke : 從名單拿掉} {--list : 列出名單} {--dry-run : 只看不改}';

    protected $description = '公測邀請名單：加入／拿掉／列出（只印 id）';

    public function handle(): int
    {
        if ($this->option('list')) {
            $rows = User::whereNotNull('beta_invited_at')->orderBy('id')->get(['id', 'legacy_id', 'beta_invited_at']);
            foreach ($rows as $u) {
                $this->line(sprintf('#%d｜舊站 %s｜%s', $u->id, $u->legacy_id ?? '－', $u->beta_invited_at));
            }
            $this->info(sprintf('名單共 %d 人。', $rows->count()));

            return self::SUCCESS;
        }

        $ids = array_map('intval', (array) $this->option('id'));
        $legacy = array_map('intval', (array) $this->option('legacy'));
        if ($file = $this->option('file')) {
            if (! is_readable($file)) {
                $this->error('讀不到名單檔。');

                return self::FAILURE;
            }
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (preg_match('/^L(\d+)$/i', $line, $m)) {
                    $legacy[] = (int) $m[1];
                } elseif (ctype_digit($line)) {
                    $ids[] = (int) $line;
                } else {
                    $this->error("名單檔有看不懂的一行（只收數字或 L＋數字），沒有改任何人：{$line}");

                    return self::FAILURE;
                }
            }
        }
        if (! $ids && ! $legacy) {
            $this->error('沒有指定任何人（--id、--legacy 或 --file）。');

            return self::FAILURE;
        }

        $users = User::whereIn('id', $ids ?: [0])->orWhereIn('legacy_id', $legacy ?: [0])->get();
        $foundIds = $users->pluck('id')->all();
        $foundLegacy = $users->pluck('legacy_id')->filter()->all();
        $missing = array_merge(
            array_map(fn ($i) => "#{$i}", array_diff($ids, $foundIds)),
            array_map(fn ($i) => "舊站 {$i}", array_diff($legacy, $foundLegacy)),
        );
        if ($missing) {
            $this->error('找不到這些會員，沒有改任何人：' . implode('、', $missing));

            return self::FAILURE;
        }

        $revoke = (bool) $this->option('revoke');
        $changed = 0;
        foreach ($users as $u) {
            $already = $u->beta_invited_at !== null;
            if ($revoke ? ! $already : $already) {
                continue;
            }
            $changed++;
            if (! $this->option('dry-run')) {
                $u->forceFill(['beta_invited_at' => $revoke ? null : now()])->save();
            }
        }
        $this->info(sprintf('%s%s %d 人（指定 %d 人，其餘本來就%s）。',
            $this->option('dry-run') ? '（試跑）' : '',
            $revoke ? '拿掉' : '加入', $changed, $users->count(), $revoke ? '不在名單' : '在名單'));

        return self::SUCCESS;
    }
}
