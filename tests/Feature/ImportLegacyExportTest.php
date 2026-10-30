<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportLegacyExportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('avatars');
        $this->dir = sys_get_temp_dir() . '/lu2026_export_test_' . getmypid();
        @mkdir($this->dir . '/photos', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/photos/*') ?: [] as $f) {
            @unlink($f);
        }
        @unlink($this->dir . '/export.json');
        @rmdir($this->dir . '/photos');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function write(array $members): void
    {
        file_put_contents($this->dir . '/export.json', json_encode(['members' => $members], JSON_UNESCAPED_UNICODE));
    }

    private function jpeg(int $w = 500, int $h = 400): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagejpeg($img);

        return ob_get_clean();
    }

    public function test_imports_member_with_hashed_password_and_pending_photo(): void
    {
        file_put_contents($this->dir . '/photos/1000014.img', $this->jpeg());
        $this->write([[
            'legacy_id' => 1000014, 'email' => 'Staff@Example.com', 'password_hash' => password_hash('old-pass', PASSWORD_BCRYPT),
            'nickname' => '同工甲', 'gender' => '男', 'birth_year' => 1980, 'live_place' => '台北市',
            'church' => '', 'church_visible' => false, 'job' => '牧師', 'intro' => '你好', 'photo' => 'photos/1000014.img',
        ]]);

        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();

        $u = User::where('legacy_id', 1000014)->firstOrFail();
        $this->assertSame('staff@example.com', $u->email);
        $this->assertFalse($u->is_demo);
        $this->assertNotNull($u->email_verified_at);
        $this->assertTrue(Hash::check('old-pass', $u->password)); // 存的是匯出時的雜湊，沒有被再雜湊一次
        $this->assertSame('pending', $u->profile->photo_status);
        Storage::disk('avatars')->assertExists($u->profile->photo_path);
        $this->assertSame(600, getimagesizefromstring(Storage::disk('avatars')->get($u->profile->photo_path))[0]);

        // 重跑不會多一筆，舊照片換掉
        $old = $u->profile->photo_path;
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame(1, User::where('legacy_id', 1000014)->count());
        Storage::disk('avatars')->assertMissing($old);

        // 可以用舊密碼登入
        $this->post('/login', ['email' => 'staff@example.com', 'password' => 'old-pass'])->assertRedirect('/diaries');
    }

    public function test_rejects_plaintext_or_bad_rows_and_path_tricks(): void
    {
        $this->write([
            ['legacy_id' => 1000017, 'email' => 'a@example.com', 'password_hash' => 'plaintext', 'nickname' => 'x'],
            ['legacy_id' => 12, 'email' => 'b@example.com', 'password_hash' => password_hash('p', PASSWORD_BCRYPT), 'nickname' => 'y'],
            ['legacy_id' => 1018068, 'email' => 'c@example.com', 'password_hash' => password_hash('p', PASSWORD_BCRYPT), 'nickname' => 'z',
                'photo' => '../../etc/passwd'],
        ]);

        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();

        $this->assertNull(User::where('legacy_id', 1000017)->first()); // 不是雜湊就不收
        $this->assertNull(User::where('legacy_id', 12)->first());
        $u = User::where('legacy_id', 1018068)->firstOrFail();
        $this->assertNull($u->profile->photo_path); // 奇怪的照片路徑直接略過
    }

    public function test_dry_run_writes_nothing_and_missing_file_fails(): void
    {
        $this->write([['legacy_id' => 1018498, 'email' => 'd@example.com', 'password_hash' => password_hash('p', PASSWORD_BCRYPT), 'nickname' => 'w']]);
        $this->artisan('legacy:import-export', ['dir' => $this->dir, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, User::count());

        $this->artisan('legacy:import-export', ['dir' => $this->dir . '/nope'])->assertFailed();
    }

    public function test_imports_legacy_phone(): void
    {
        $m = fn (int $id, $phone) => ['legacy_id' => $id, 'email' => "m{$id}@example.com", 'password_hash' => password_hash('x', PASSWORD_BCRYPT),
            'nickname' => "會員{$id}", 'gender' => '女', 'birth_year' => 1990, 'live_place' => '', 'church' => '', 'church_visible' => false,
            'job' => '', 'intro' => null, 'photo' => null, 'phone' => $phone];
        User::factory()->create(['phone' => '0933333333']); // 新版已經有人用這支
        $this->write([
            $m(1000001, ['country_code' => '886', 'mobile' => '0911111111', 'verified_at' => now()->subMonths(2)->format('Y-m-d H:i:s')]),
            $m(1000002, ['country_code' => '852', 'mobile' => '091234567', 'verified_at' => now()->subMonths(14)->format('Y-m-d H:i:s')]),
            $m(1000003, null), // 舊站沒有認證過
            $m(1000004, ['country_code' => '886', 'mobile' => '0933333333', 'verified_at' => now()->subMonth()->format('Y-m-d H:i:s')]),
        ]);
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->expectsOutputToContain('1000004')->assertSuccessful();

        $u = fn (int $id) => User::where('legacy_id', $id)->first();
        $this->assertSame('0911111111', $u(1000001)->phone);
        $this->assertTrue($u(1000001)->hasValidPhone());
        $this->assertSame('+852-91234567', $u(1000002)->phone); // 搬過來，但超過 12 個月：登入時要重新認證
        $this->assertFalse($u(1000002)->hasValidPhone());
        $this->assertNull($u(1000003)->phone);
        $this->assertNull($u(1000003)->phone_verified_at);
        $this->assertNull($u(1000004)->phone); // 一支手機一個帳號
        $this->assertNull($u(1000004)->phone_verified_at);
    }

    public function test_legacy_status_maps_to_active_unverified_suspended(): void
    {
        $m = function (int $id, ?int $status): array {
            $row = ['legacy_id' => $id, 'email' => "s{$id}@example.com", 'password_hash' => password_hash('old-pass', PASSWORD_BCRYPT),
                'nickname' => "會員{$id}", 'gender' => '女', 'birth_year' => 1990, 'live_place' => '', 'church' => '', 'church_visible' => false,
                'job' => '', 'intro' => null, 'photo' => null];

            return $status === null ? $row : $row + ['status' => $status]; // null＝舊版匯出，沒有 status 欄
        };
        $this->write([$m(1000101, 2), $m(1000102, 4), $m(1000103, 1), $m(1000104, -1), $m(1000105, 9), $m(1000106, null)]);
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();
        $u = fn (int $id) => User::where('legacy_id', $id)->first();

        $this->assertNotNull($u(1000101)->email_verified_at);
        $this->assertNull($u(1000101)->suspended_at);
        $this->assertSame(4, $u(1000102)->legacy_status);
        $this->assertNotNull($u(1000102)->email_verified_at); // 社群會員也是一般會員
        $this->assertNull($u(1000103)->email_verified_at);    // 未啟用：要重新驗證
        $this->assertNull($u(1000103)->suspended_at);
        foreach ([1000104, 1000105] as $id) {                  // 停權、不明的值
            $this->assertNotNull($u($id)->suspended_at);
            $this->assertFalse((bool) $u($id)->is_active);
        }
        $this->assertSame(2, $u(1000106)->legacy_status); // 舊版匯出沒有 status：當 2
        $this->assertNull($u(1000106)->suspended_at);

        // 停權的：密碼對也不能登入，說停權；密碼錯照樣說「不正確」
        $this->post('/login', ['email' => 's1000104@example.com', 'password' => 'wrong'])->assertSessionHasErrors(['email' => '電子郵件或密碼不正確。']);
        $this->post('/login', ['email' => 's1000104@example.com', 'password' => 'old-pass'])
            ->assertSessionHasErrors(['email' => '這個帳號已經停權。如果有疑問，請寫信到 support@loveuno.com 跟我們聯繫。']);
        $this->assertGuest();
        $this->post('/login', ['email' => 's1000101@example.com', 'password' => 'old-pass'])->assertRedirect();
        $this->assertAuthenticated();
    }
}
