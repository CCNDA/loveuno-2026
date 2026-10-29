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
}
