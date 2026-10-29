<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\User;
use App\Support\AvatarImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

// 匯入 tools/legacy_export.php 在 vm10 匯出的「指定會員」（熊叔叔 10/29 指定的協會同工帳號，Asana 1215470856990770）。
// - 密碼在匯出時就已經雜湊，這裡直接存雜湊
// - 照片照上傳的規矩重做成 600×600 JPEG（拿掉 EXIF）；狀態是 pending，要管理員審核才給其他會員看
// - 不是示範資料（is_demo=false），重種示範資料不會刪到；email 已驗證（舊站會員）
// 不印 email、密碼，只印編號與筆數。
class ImportLegacyExport extends Command
{
    protected $signature = 'legacy:import-export {dir : legacy_export.php 的輸出目錄} {--dry-run : 只檢查，不寫入}';

    protected $description = '匯入在舊站匯出的指定會員（帳號、個人檔案、主照）';

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        $json = @file_get_contents("{$dir}/export.json");
        $data = $json ? json_decode($json, true) : null;
        if (! is_array($data) || ! isset($data['members']) || ! is_array($data['members'])) {
            $this->error('讀不到 export.json，或格式不對。');

            return self::FAILURE;
        }

        $done = [];
        $skipped = [];
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
            if ($this->option('dry-run')) {
                $done[] = $id;

                continue;
            }

            DB::transaction(function () use ($m, $id, $email, $hash, $dir) {
                $user = User::firstOrNew(['legacy_id' => $id]);
                $nick = trim((string) ($m['nickname'] ?? ''));
                $user->name = $nick !== '' ? mb_substr($nick, 0, 50) : '會員' . $id;
                $user->email = $email;
                $user->is_demo = false;
                if (! $user->exists) {
                    $user->password = Str::random(64); // 欄位不能空，先放亂數，下一行換成匯出的雜湊
                }
                $user->save();
                // 直接寫雜湊（不經過 hashed cast，避免被再雜湊一次）
                DB::table('users')->where('id', $user->id)->update(['password' => $hash, 'email_verified_at' => $user->email_verified_at ?? now()]);

                $profile = Profile::updateOrCreate(['user_id' => $user->id], [
                    'gender' => in_array($m['gender'] ?? '', ['男', '女'], true) ? $m['gender'] : '',
                    'birth_year' => ((int) ($m['birth_year'] ?? 0)) ?: null,
                    'live_place' => mb_substr((string) ($m['live_place'] ?? ''), 0, 20),
                    'church' => mb_substr((string) ($m['church'] ?? ''), 0, 100),
                    'church_visible' => (bool) ($m['church_visible'] ?? false),
                    'job' => mb_substr((string) ($m['job'] ?? ''), 0, 20),
                    'intro' => $m['intro'] ?? null,
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

        $this->info(sprintf('%s %d 位：%s', $this->option('dry-run') ? '可以匯入' : '已匯入', count($done), implode(', ', $done)));
        if ($skipped) {
            $this->warn(sprintf('略過 %d 位：%s', count($skipped), implode('；', $skipped)));
        }

        return self::SUCCESS;
    }
}
