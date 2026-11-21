<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Support\BetaChangeRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// 公測增量同步（舊→新）：legacy:import-export --update-from-legacy（Asana 1220564106252789）
class LegacyIncrementalImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['loveuno.beta_audit' => true]);
        BetaChangeRecorder::$importing = false;
        $this->dir = sys_get_temp_dir() . '/lu-inc-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700);
    }

    protected function tearDown(): void
    {
        @unlink("{$this->dir}/export.json");
        @rmdir($this->dir);
        BetaChangeRecorder::$importing = false;
        parent::tearDown();
    }

    private function member(int $lid): User
    {
        $u = User::factory()->create(['legacy_id' => $lid, 'name' => '舊名', 'email' => "m{$lid}@example.com"]);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'marital' => '未婚', 'job' => '原職業', 'live_place' => '台北']);

        return $u;
    }

    private function export(int $lid, array $over = []): void
    {
        file_put_contents("{$this->dir}/export.json", json_encode(['members' => [array_merge([
            'legacy_id' => $lid, 'status' => 2, 'email' => "m{$lid}@example.com", 'password_hash' => password_hash('x', PASSWORD_BCRYPT),
            'nickname' => '舊站新名', 'gender' => '女', 'birth_year' => 1990, 'live_place' => '台中', 'church' => '', 'church_visible' => false,
            'job' => '舊站新職業', 'intro' => '舊站自介', 'photo' => null,
        ], $over)]], JSON_UNESCAPED_UNICODE));
    }

    private function run_(): void
    {
        $this->artisan('legacy:import-export', ['dir' => $this->dir, '--update-from-legacy' => true])->assertSuccessful();
    }

    public function test_untouched_fields_follow_legacy_and_nothing_goes_back_to_outbox(): void
    {
        $u = $this->member(1000701);
        DB::table('legacy_outbox')->delete();
        $this->export(1000701);
        $this->run_();
        $u->refresh();
        $this->assertSame('舊站新名', $u->name);
        $this->assertSame('舊站新職業', $u->profile->job);
        $this->assertSame('台中', $u->profile->live_place);
        $this->assertSame(0, DB::table('legacy_outbox')->count(), '從舊站來的不排回寫（防迴圈）');
        $this->assertGreaterThan(0, DB::table('beta_audits')->where('actor', 'sync')->count(), '照樣記 audit，標 sync');
        $this->assertFalse(BetaChangeRecorder::$importing, '指令結束後恢復');
    }

    public function test_fields_member_changed_in_new_site_win(): void
    {
        $u = $this->member(1000702);
        $this->actingAs($u);
        $u->forceFill(['name' => '新版自己改的'])->save();
        $u->profile->update(['job' => '新版職業']);
        auth()->logout();
        $this->export(1000702);
        $this->run_();
        $u->refresh();
        $this->assertSame('新版自己改的', $u->name);
        $this->assertSame('新版職業', $u->profile->job);
        $this->assertSame('台中', $u->profile->live_place, '新版沒動的欄位照舊站更新');
    }

    public function test_email_and_password_untouched_and_suspension_applied(): void
    {
        $u = $this->member(1000703);
        $pw = $u->password;
        $this->export(1000703, ['email' => 'changed@example.com', 'status' => -1]);
        $this->run_();
        $u->refresh();
        $this->assertSame('m1000703@example.com', $u->email);
        $this->assertSame($pw, $u->password);
        $this->assertNotNull($u->suspended_at);
    }

    public function test_without_flag_existing_members_unchanged(): void
    {
        $u = $this->member(1000704);
        $this->export(1000704);
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame('舊名', $u->fresh()->name);
    }
}
