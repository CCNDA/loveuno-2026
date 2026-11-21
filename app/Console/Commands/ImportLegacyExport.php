<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\User;
use App\Support\AvatarImage;
use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

// 匯入 tools/legacy_export.php 在 vm10 匯出的「指定會員」（熊叔叔 10/29 指定的協會同工帳號，Asana 1215470856990770）。
// - 密碼在匯出時就已經雜湊，這裡直接存雜湊
// - 照片照上傳的規矩重做成 600×600 JPEG（拿掉 EXIF）；狀態是 pending，要管理員審核才給其他會員看
// - 不是示範資料（is_demo=false），重種示範資料不會刪到；email 已驗證（舊站會員）
// - 舊站狀態（熊叔叔 10/30「3都搬」）：2 交友會員、4 社群會員、6 特約作者 → 一般會員；1／3／5 還沒啟用 → 要重新驗證 email；
//   負數 → 停權（suspended_at、is_active = false，不能登入）；0、9 等其他值照舊站 member/login.php 可以登入 → 一般會員。
//   export.json 沒有 status（舊版匯出）就當 2。
// - 已經搬過的會員（同一個 legacy_id）不再改：會員在新版改過的名字、email、密碼、檔案都不會被蓋回去（要重搬用 --refresh）
// - 手機（熊叔叔 10/29「2d手機號碼一起搬」）：舊站認證過的號碼和認證日一起搬，超過 12 個月的登入時要重新認證；
//   舊站沒有認證過的要在新版認證。同一支手機已經在別的帳號就不搬（一支手機一個帳號，「2c」），印編號。
//   export.json 沒有 phone 欄位（舊版匯出、或唯讀帳號沒有 certification 權限）就照以前：先當作已認證。
// 不印 email、密碼，只印編號與筆數。
class ImportLegacyExport extends Command
{
    protected $signature = 'legacy:import-export {dir : legacy_export.php 的輸出目錄} {--dry-run : 只檢查，不寫入} {--refresh : 已經搬過的也照匯出檔重寫（只在正式切換前演練用）} {--update-from-legacy : 公測增量：已經搬過的，用舊站的值更新新版沒改過的欄位}';

    protected $description = '匯入在舊站匯出的指定會員（帳號、個人檔案、主照）';

    public function handle(): int
    {
        // 從舊站匯入：標成 sync → 照樣記 audit、不排回寫（防兩邊來回打轉；11/21 發現原本沒標。Asana 1220564106252789）
        \App\Support\BetaChangeRecorder::$importing = true;
        try {
            return $this->runImport();
        } finally {
            \App\Support\BetaChangeRecorder::$importing = false;
        }
    }

    private function runImport(): int
    {
        if ($this->option('update-from-legacy') && ! config('loveuno.beta_audit')) {
            // 分辨「會員在新版改過」靠 beta_audits；沒開就會把新版的修改蓋掉
            $this->error('--update-from-legacy 要在 BETA_AUDIT=true 的站才能用（靠寫入紀錄分辨新版改過的）。');

            return self::FAILURE;
        }
        $dir = rtrim($this->argument('dir'), '/');
        $json = @file_get_contents("{$dir}/export.json");
        $data = $json ? json_decode($json, true) : null;
        if (! is_array($data) || ! isset($data['members']) || ! is_array($data['members'])) {
            $this->error('讀不到 export.json，或格式不對。');

            return self::FAILURE;
        }

        $done = [];
        $skipped = [];
        $phoneSkipped = [];
        $existing = 0;
        $updated = 0;
        $placeholder = \Illuminate\Support\Facades\Hash::make(Str::random(40));
        foreach ($data['members'] as $m) {
            $id = (int) ($m['legacy_id'] ?? 0);
            $hash = (string) ($m['password_hash'] ?? '');
            $email = mb_strtolower(trim((string) ($m['email'] ?? '')));
            if ($id < 1000000 || $email === '' || ! str_starts_with($hash, '$2y$')) {
                $skipped[] = "{$id}（資料不完整）";

                continue;
            }
            if (User::where('email', $email)->where(fn ($w) => $w->whereNull('legacy_id')->orWhere('legacy_id', '<>', $id))->exists()) {
                $skipped[] = "{$id}（email 已被別的帳號用了）";

                continue;
            }
            // 已經搬過的：會員可能在新版改過名字、email、密碼，不要蓋回舊站的（審查 10/30）
            if (! $this->option('refresh') && ($had = User::withoutGlobalScopes()->where('legacy_id', $id)->first())) {
                $existing++;
                if ($this->option('update-from-legacy') && ! $this->option('dry-run') && DB::transaction(fn () => $this->updateFromLegacy($had, $m))) {
                    $updated++;
                }

                continue;
            }
            if ($this->option('dry-run')) {
                $done[] = $id;

                continue;
            }

            DB::transaction(function () use ($m, $id, $email, $hash, $dir, $placeholder, &$phoneSkipped) {
                $user = User::firstOrNew(['legacy_id' => $id]);
                if ($user->deleted_at !== null) {
                    return; // 會員自己註銷了：不要再匯回來
                }
                $nick = trim((string) ($m['nickname'] ?? ''));
                $user->name = $nick !== '' ? mb_substr($nick, 0, 50) : '會員' . $id;
                $user->email = $email;
                $user->is_demo = false;
                if (! $user->exists) {
                    // 欄位不能空，先放一個佔位的雜湊，下一行換成匯出的雜湊。佔位雜湊整批只算一次：
                    // 原本每位都 Hash::make(亂數)，bcrypt 一位 0.2 秒，演練 1,203 位要 242 秒、2 萬位要一個多小時（10/31 演練發現）
                    $user->password = $placeholder;
                }
                $user->save();
                // 直接寫雜湊（不經過 hashed cast，避免被再雜湊一次）
                $status = (int) ($m['status'] ?? 2);
                $kind = self::statusKind($status);
                DB::table('users')->where('id', $user->id)->update([
                    'password' => $hash,
                    'legacy_status' => $status,
                    // 未啟用的不幫他驗證（舊站本來就還沒啟用）；新版已經驗證過的不改回去
                    'email_verified_at' => $kind === 'unverified' ? $user->email_verified_at : ($user->email_verified_at ?? now()),
                ]);
                if ($kind === 'suspended') {
                    DB::table('users')->where('id', $user->id)->whereNull('suspended_at')->update(['suspended_at' => now(), 'is_active' => false]);
                }
                if (! array_key_exists('phone', $m)) {
                    DB::table('users')->where('id', $user->id)->whereNull('phone_verified_at')->update(['phone_verified_at' => now()]);
                } elseif ($user->phone === null) { // 新版已經認證過號碼的不蓋掉
                    [$phone, $at] = $this->legacyPhone($m['phone']);
                    if ($phone !== null && User::where('phone', $phone)->whereKeyNot($user->id)->exists()) {
                        $phoneSkipped[] = $id;
                        $phone = $at = null;
                    }
                    DB::table('users')->where('id', $user->id)->update(['phone' => $phone, 'phone_verified_at' => $at]);
                }

                $profile = Profile::updateOrCreate(['user_id' => $user->id], [
                    'gender' => in_array($m['gender'] ?? '', ['男', '女'], true) ? $m['gender'] : '',
                    'birth_year' => ((int) ($m['birth_year'] ?? 0)) ?: null,
                    'live_place' => mb_substr((string) ($m['live_place'] ?? ''), 0, 20),
                    'church' => mb_substr((string) ($m['church'] ?? ''), 0, 100),
                    'church_visible' => (bool) ($m['church_visible'] ?? false),
                    'job' => mb_substr((string) ($m['job'] ?? ''), 0, 20),
                    'intro' => \App\Support\LegacyText::plain($m['intro'] ?? null), // 舊站存 <br>（熊叔叔 11/6）
                ]);

                $photo = (string) ($m['photo'] ?? '');
                if ($photo !== '' && preg_match('#^photos/\d{7}\.img$#', $photo) && is_file("{$dir}/{$photo}")) {
                    try {
                        $jpeg = AvatarImage::fromUpload("{$dir}/{$photo}");
                        $old = $profile->photo_path;
                        $path = Str::random(40) . '.jpg';
                        Storage::disk('avatars')->put($path, $jpeg);
                        $profile->forceFill(['photo_path' => $path, 'photo_status' => 'pending', 'photo_updated_at' => now()])->save();
                        if ($old && $old !== $path) {
                            Storage::disk('avatars')->delete($old);
                        }
                    } catch (RuntimeException) {
                        $this->warn("{$id} 的照片讀不出來，略過照片");
                    }
                }
            });
            $done[] = $id;
        }

        $this->info(sprintf('%s %d 位%s；%s', $this->option('dry-run') ? '可以匯入' : '已匯入', count($done),
            count($done) <= 50 ? '：' . implode(', ', $done) : '',
            $this->option('update-from-legacy') ? "已經搬過 {$existing} 位（其中 {$updated} 位用舊站的新資料更新）" : "已經搬過、沒動 {$existing} 位"));
        if ($skipped) {
            $this->warn(sprintf('略過 %d 位：%s', count($skipped), implode('；', $skipped)));
        }
        if ($phoneSkipped) {
            $this->warn(sprintf('手機已經在別的帳號、沒有搬的 %d 位（要在新版重新認證）：%s', count($phoneSkipped), implode(', ', $phoneSkipped)));
        }

        return self::SUCCESS;
    }

    // 公測增量同步（Asana 1220564106252789）：已經搬過的會員，用舊站的新值更新新版。
    // - 會員在新版自己改過的欄位（beta_audits 有 actor=user:… 的紀錄）以新版為準，不蓋
    // - 不動 email、密碼、手機（安全相關；新版改密碼不會留內容紀錄，分不出誰新）、照片（要重新審核，另外處理）
    // - 舊站停權（status 負數）→ 新版也停權；新版註銷的不動
    // 回傳有沒有改到東西
    private function updateFromLegacy(User $user, array $m): bool
    {
        if ($user->deleted_at !== null) {
            return false;
        }
        $owned = fn (string $table, $recordId) => DB::table('beta_audits')->where('table_name', $table)->where('record_id', (string) $recordId)
            ->where('actor', 'like', 'user:%')->pluck('after')
            ->flatMap(fn ($a) => array_keys(json_decode((string) $a, true) ?: []))->unique()->all();
        $changed = false;

        $nick = trim((string) ($m['nickname'] ?? ''));
        if ($nick !== '' && ! in_array('name', $owned($user->getTable(), $user->getKey()), true) && $user->name !== mb_substr($nick, 0, 50)) {
            $user->name = mb_substr($nick, 0, 50);
            $user->save();
            $changed = true;
        }
        $status = (int) ($m['status'] ?? 2);
        if (self::statusKind($status) === 'suspended' && $user->suspended_at === null) {
            DB::table('users')->where('id', $user->id)->update(['suspended_at' => now(), 'is_active' => false, 'legacy_status' => $status]);
            $changed = true;
        }

        $profile = $user->profile;
        if ($profile !== null) {
            $mine = $owned($profile->getTable(), $profile->getKey());
            $want = [
                'gender' => in_array($m['gender'] ?? '', ['男', '女'], true) ? $m['gender'] : $profile->gender,
                'live_place' => mb_substr((string) ($m['live_place'] ?? ''), 0, 20),
                'church' => mb_substr((string) ($m['church'] ?? ''), 0, 100),
                'church_visible' => (bool) ($m['church_visible'] ?? false),
                'job' => mb_substr((string) ($m['job'] ?? ''), 0, 20),
                'intro' => \App\Support\LegacyText::plain($m['intro'] ?? null),
            ];
            foreach ($want as $k => $v) {
                if (! in_array($k, $mine, true)) {
                    $profile->{$k} = $v;
                }
            }
            if ($profile->isDirty()) {
                $profile->save();
                $changed = true;
            }
        }

        return $changed;
    }

    // 舊站的號碼 → [新版的寫法, 認證時間]；格式不對、沒有認證時間就 [null, null]（要在新版認證）
    private function legacyPhone(?array $p): array
    {
        if (! $p) {
            return [null, null];
        }
        [$phone] = Phone::normalize((string) ($p['country_code'] ?: '886'), (string) ($p['mobile'] ?? ''));
        $at = (string) ($p['verified_at'] ?? '');
        if ($phone === null || $at === '' || str_starts_with($at, '0000')) {
            return [null, null];
        }
        try {
            return [$phone, Carbon::parse($at)];
        } catch (Throwable) {
            return [null, null];
        }
    }

    // 舊站 basic.status → active（一般會員）／unverified（還沒啟用）／suspended（停權，或不明的值）
    public static function statusKind(int $status): string
    {
        return match (true) {
            in_array($status, [1, 3, 5], true) => 'unverified',
            $status < 0 => 'suspended',
            default => 'active', // 2、4、6，還有 0、9 這類舊站也能登入的
        };
    }
}
