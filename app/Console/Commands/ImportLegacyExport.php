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
                ] + self::legacyProfileFields($m['legacy_profile'] ?? []));

                $photo = (string) ($m['photo'] ?? '');
                if (\App\Support\PhotoStore::isLegacy($photo)) {
                    // 舊站 R2 原檔直接用（熊叔叔 11/22 選甲）：路徑要合規才收
                    $ref = \App\Support\PhotoStore::legacyRef('member_photo/image', substr($photo, strlen('legacy:member_photo/image/')));
                    if ($ref !== null && $ref === $photo && $profile->photo_path !== $ref) {
                        $old = $profile->photo_path;
                        $profile->forceFill(['photo_path' => $ref, 'photo_status' => 'pending', 'photo_updated_at' => now()])->save();
                        \App\Support\PhotoStore::delete($old);
                    }
                } elseif ($photo !== '' && preg_match('#^photos/\d{7}\.img$#', $photo) && is_file("{$dir}/{$photo}")) {
                    try {
                        $jpeg = AvatarImage::fromUpload("{$dir}/{$photo}");
                        $old = $profile->photo_path;
                        $path = Str::random(40) . '.jpg';
                        Storage::disk('avatars')->put($path, $jpeg);
                        $profile->forceFill(['photo_path' => $path, 'photo_status' => 'pending', 'photo_updated_at' => now()])->save();
                        if ($old && $old !== $path) {
                            \App\Support\PhotoStore::delete($old);
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
            ] + self::legacyProfileFields($m['legacy_profile'] ?? []);
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

    // 舊站 profile 其他欄位 → 新版（熊叔叔 11/22：「直接切換，舊資料都要在」「規則改變很小，只是改變名稱」）
    // 新版的選項就是照舊站做的（ProfileOptions、LoveHistory 的 key 沿用舊站欄位名），所以多半一對一；
    // 不在新版選項裡的值不收（留空，會員自己再選）；交友條件的「不拘」／空白＝沒設定；數字超出新版允許範圍的不收。
    // 沒帶 legacy_profile（11/22 以前的匯出檔）就回空陣列，什麼都不動。
    public static function legacyProfileFields(array $p): array
    {
        if ($p === []) {
            return [];
        }
        $O = \App\Support\ProfileOptions::class;
        $pick = fn ($v, array $allowed) => in_array(trim((string) $v), $allowed, true) ? trim((string) $v) : null;
        $num = fn ($v, int $lo, int $hi) => (is_numeric($v) && (int) $v >= $lo && (int) $v <= $hi) ? (int) $v : null;
        $many = function (array $vals, array $allowed) {
            $vals = array_map(fn ($v) => str_replace('中文/普通话', '中文/普通話', trim((string) $v)), $vals);
            return array_values(array_unique(array_filter($vals, fn ($v) => in_array($v, $allowed, true))));
        };
        $any = fn ($v) => in_array(trim((string) $v), ['', '不拘'], true) ? null : trim((string) $v);
        $out = [
            'real_name' => mb_substr(trim((string) ($p['realname'] ?? '')), 0, 30),
            'home_phone' => (function ($v) {   // 跟 ProfileController 同一個 regex；「分機」、中文字這類不符就留空（小布 11/22）
                $v = trim(strtr((string) $v, ['（' => '(', '）' => ')', '－' => '-', '＃' => '#', '＋' => '+']));
                return (mb_strlen($v) <= 20 && preg_match('/^[0-9+\-() #]*$/', $v)) ? $v : '';
            })($p['homephone'] ?? ''),
            'marital' => $pick($p['marriage'] ?? '', array_merge($O::MARITAL, $O::MARITAL_ADMIN)),
            'children' => $pick($p['kids'] ?? '', $O::CHILDREN),
            'faith' => $pick($p['religion'] ?? '', $O::FAITH),
            'education' => $pick($p['education'] ?? '', $O::EDUCATION),
            'blood_type' => $pick($p['blood'] ?? '', $O::BLOOD),
            'smoking' => $pick($p['smoke'] ?? '', $O::SMOKING),
            'drinking' => $pick($p['drink'] ?? '', $O::DRINKING),
            'height' => $num($p['height'] ?? null, 120, 230),   // 跟 ProfileController 驗證一致（小布 11/22：90～119 匯進來之後改資料會被擋）
            'weight' => $num($p['weight'] ?? null, 30, 200),
            'languages' => $many([$p['language1'] ?? '', $p['language2'] ?? '', $p['language3'] ?? ''], $O::LANGUAGES),
            'hobbies' => $many([$p['hobby1'] ?? '', $p['hobby2'] ?? '', $p['hobby3'] ?? ''], $O::HOBBIES),
            'sports' => $many([$p['sports1'] ?? '', $p['sports2'] ?? '', $p['sports3'] ?? ''], $O::SPORTS),
            'pref_gender' => $pick($p['hope_sex'] ?? '', ['男', '女']),
            'pref_place' => ($v = $any($p['hope_live_place'] ?? '')) === null ? null : mb_substr($v, 0, 20),
            'pref_marital' => $pick($any($p['hope_marriage'] ?? '') ?? '', $O::MARITAL),
            'pref_faith' => $pick($any($p['hope_religion'] ?? '') ?? '', $O::FAITH),
        ];
        // 年齡／身高／體重區間：兩端都在新版範圍內、而且小的在前才收（舊站有 10 歲這種值）
        foreach ([['age', 18, 99], ['height', 120, 230], ['weight', 30, 200]] as [$k, $lo, $hi]) {
            $a = $num($p["hope_{$k}1"] ?? null, $lo, $hi);
            $b = $num($p["hope_{$k}2"] ?? null, $lo, $hi);
            [$out["pref_{$k}_min"], $out["pref_{$k}_max"]] = ($a !== null && $b !== null && $a > $b) ? [null, null] : [$a, $b];
        }
        if (preg_match('/^\d{4}-(\d{2})-(\d{2})/', (string) ($p['birthday'] ?? ''), $d) && checkdate((int) $d[1], (int) $d[2], 2000)) {   // 跟表單一樣（小布 11/22）
            $out['birth_month'] = (int) $d[1];
            $out['birth_day'] = (int) $d[2];
        }
        $verse = trim(\App\Support\LegacyText::plain($p['bible'] ?? null));
        if ($verse !== '' && $verse !== '沒有選擇最愛經句') {
            $out['verse_text'] = mb_substr($verse, 0, 300);
        }
        $love = [];
        foreach (\App\Support\LoveHistory::QUESTIONS as $k => [, $opts]) {
            if (($v = $pick($p[$k] ?? '', $opts)) !== null) {
                $love[$k] = $v;
            }
        }
        foreach (\App\Support\LoveHistory::TEXTS as $k => [, $max]) {
            $v = trim(\App\Support\LegacyText::plain($p[$k] ?? null));
            if ($v !== '') {
                $love[$k] = mb_substr($v, 0, $max);
            }
        }
        if ($love !== []) {
            $out['love_history'] = $love;
        }
        // 這些文字欄位資料表是 NOT NULL、預設空字串：沒對到就存空字串
        foreach (['marital', 'children', 'faith', 'education', 'blood_type', 'smoking', 'drinking', 'pref_gender', 'pref_place', 'pref_marital', 'pref_faith'] as $k) {
            $out[$k] = $out[$k] ?? '';
        }

        return $out;
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
