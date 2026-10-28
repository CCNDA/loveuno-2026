<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// 把舊站 basic／profile 的帳號匯進新站（Asana 1215470856990770）。
// 舊站密碼是明文（Asana 1218196609173270），這裡讀出來後立刻雜湊，新站只存雜湊。
// 🔴 展示版只准連舊站「測試庫」：庫名沒有 dev／test 這一段就拒絕。正式庫的搬遷另外設計、另外審。
// 不印 email、密碼，只印 id 與筆數。
class ImportLegacyAccounts extends Command
{
    protected $signature = 'legacy:import-accounts {--id=* : 只匯這些舊站 id} {--dry-run : 只算筆數，不寫入}';

    protected $description = '從舊站測試庫匯入會員帳號（密碼改存雜湊）';

    public function handle(): int
    {
        $db = (string) config('database.connections.legacy.database');
        if (! preg_match('/(^|_)(dev|test)(_|$)/', $db)) {
            $this->error('舊站資料庫名稱裡沒有獨立的 dev 或 test 一段（例如 loveuno_dev_member），不像測試庫，停止。展示版不可以讀正式會員資料。');

            return self::FAILURE;
        }

        $q = DB::connection('legacy')->table('basic as b')
            ->leftJoin('profile as p', 'p.id', '=', 'b.id')
            ->select('b.id', 'b.email', 'b.password', 'b.nickname',
                'p.sex', 'p.birthday', 'p.live_place', 'p.church', 'p.church_show', 'p.job', 'p.intro')
            ->orderBy('b.id');
        if ($ids = $this->option('id')) {
            $q->whereIn('b.id', array_map('intval', $ids));
        }

        $done = [];
        $skipped = [];
        foreach ($q->cursor() as $row) {
            $email = mb_strtolower(trim((string) $row->email));
            $plain = (string) $row->password;
            if ($email === '' || $plain === '') {
                $skipped[] = "{$row->id}（沒有 email 或密碼）";

                continue;
            }
            $taken = User::where('email', $email)->where(function ($w) use ($row) {
                $w->whereNull('legacy_id')->orWhere('legacy_id', '<>', $row->id);
            })->exists();
            if ($taken) {
                $skipped[] = "{$row->id}（email 已被別的帳號用了）";

                continue;
            }
            if ($this->option('dry-run')) {
                $done[] = $row->id;

                continue;
            }

            DB::transaction(function () use ($row, $email, $plain) {
                $user = User::firstOrNew(['legacy_id' => $row->id]);
                $user->name = trim((string) $row->nickname) !== '' ? trim((string) $row->nickname) : '會員' . $row->id;
                $user->email = $email;
                $user->password = $plain; // casts 'hashed'：存進去的是雜湊
                // 從測試庫來的都是測試帳號，跟示範資料一樣在正式開放前要刪掉
                $user->is_demo = true;
                $user->save();

                $year = $row->birthday ? (int) substr((string) $row->birthday, 0, 4) : 0;
                Profile::updateOrCreate(['user_id' => $user->id], [
                    'gender' => in_array($row->sex, ['男', '女'], true) ? $row->sex : '',
                    'birth_year' => $year > 1900 ? $year : null,
                    'live_place' => (string) $row->live_place,
                    'church' => $row->church_show ? mb_substr((string) $row->church, 0, 100) : '',
                    'church_visible' => (bool) $row->church_show,
                    'job' => (string) $row->job,
                    'intro' => $row->intro,
                ]);
            });
            $done[] = $row->id;
        }

        $verb = $this->option('dry-run') ? '可以匯入' : '已匯入';
        $this->info(sprintf('%s %d 筆：%s', $verb, count($done), implode(', ', $done)));
        if ($skipped) {
            $this->warn(sprintf('略過 %d 筆：%s', count($skipped), implode('；', $skipped)));
        }

        return self::SUCCESS;
    }
}
