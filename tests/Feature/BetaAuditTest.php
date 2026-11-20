<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\Profile;
use App\Models\User;
use App\Support\BetaChangeRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// 公測寫入紀錄（audit）與回寫待送清單（outbox）。Asana 1220586286066431、1220564106252789
class BetaAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        BetaChangeRecorder::$importing = false;
        parent::tearDown();
    }

    private function member(array $over = []): User
    {
        $u = User::factory()->create($over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'marital' => '未婚']);

        return $u;
    }

    public function test_switch_off_records_nothing(): void
    {
        config(['loveuno.beta_audit' => false]);
        $u = $this->member();
        $this->actingAs($u)->post('/diaries', ['title' => '關著', 'body' => '內容', 'visibility' => 'public']);
        $this->assertSame(0, DB::table('beta_audits')->count());
        $this->assertSame(0, DB::table('legacy_outbox')->count());
    }

    public function test_diary_create_update_delete_recorded_with_actor_and_outbox(): void
    {
        config(['loveuno.beta_audit' => true]);
        $u = $this->member();
        DB::table('beta_audits')->delete(); DB::table('legacy_outbox')->delete();
        $this->actingAs($u)->post('/diaries', ['title' => '第一篇', 'body' => '內容', 'visibility' => 'public'])->assertRedirect();
        $d = Diary::where('user_id', $u->id)->firstOrFail();
        $a = DB::table('beta_audits')->where('table_name', 'diaries')->where('action', 'created')->first();
        $this->assertNotNull($a);
        $this->assertSame("user:{$u->id}", $a->actor);
        $this->assertSame('第一篇', json_decode($a->after, true)['title']);

        $d->update(['title' => '改過']);
        $up = DB::table('beta_audits')->where('table_name', 'diaries')->where('action', 'updated')->first();
        $this->assertSame('第一篇', json_decode($up->before, true)['title']);
        $this->assertSame('改過', json_decode($up->after, true)['title']);
        $this->assertArrayNotHasKey('body', json_decode($up->after, true), '只記有改的欄位');

        $d->delete();
        $this->assertNotNull(DB::table('beta_audits')->where('table_name', 'diaries')->where('action', 'deleted')->first());
        $this->assertSame(['created', 'updated', 'deleted'],
            DB::table('legacy_outbox')->where('model', 'Diary')->orderBy('id')->pluck('action')->all());
    }

    public function test_password_never_stored_and_password_only_change_has_no_content(): void
    {
        config(['loveuno.beta_audit' => true]);
        $u = $this->member();
        $u->forceFill(['password' => Hash::make('新密碼很長很長')])->save();
        foreach (DB::table('beta_audits')->get() as $r) {
            $this->assertStringNotContainsString('password', (string) $r->before . (string) $r->after);
            $this->assertStringNotContainsString('$2y$', (string) $r->before . (string) $r->after);
        }
        $this->assertSame(0, DB::table('legacy_outbox')->where('model', 'User')->where('action', 'updated')->count(), '換密碼不回寫舊站');
    }

    public function test_user_outbox_only_when_name_changes(): void
    {
        config(['loveuno.beta_audit' => true]);
        $u = $this->member();
        DB::table('legacy_outbox')->delete();
        $u->forceFill(['last_login_at' => now()])->save();
        $this->assertSame(0, DB::table('legacy_outbox')->count());
        $u->forceFill(['name' => '新暱稱'])->save();
        $this->assertSame(1, DB::table('legacy_outbox')->where('model', 'User')->count());
    }

    public function test_importing_records_audit_as_sync_but_no_outbox(): void
    {
        config(['loveuno.beta_audit' => true]);
        BetaChangeRecorder::$importing = true;
        $u = $this->member();
        Diary::forceCreate(['user_id' => $u->id, 'title' => '舊站來的', 'body' => '內容', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $this->assertSame(0, DB::table('legacy_outbox')->count(), '從舊站匯入的不排回寫（防迴圈）');
        $this->assertSame('sync', DB::table('beta_audits')->where('table_name', 'diaries')->value('actor'));
    }

    public function test_audit_rolls_back_with_failed_transaction(): void
    {
        config(['loveuno.beta_audit' => true]);
        $u = $this->member();
        $n = DB::table('beta_audits')->count();
        $inside = null;
        try {
            DB::transaction(function () use ($u, &$inside) {
                Diary::forceCreate(['user_id' => $u->id, 'title' => '會回滾', 'body' => 'x', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
                $inside = DB::table('beta_audits')->where('table_name', 'diaries')->count();
                throw new \RuntimeException('故意失敗');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('故意失敗', $e->getMessage(), '要是我們丟的那個例外，不是別的 SQL 錯誤');
        }
        $this->assertSame(1, $inside, '交易裡面確實有寫進 audit（之後才回滾）');
        $this->assertSame($n, DB::table('beta_audits')->count());
        $this->assertSame(0, DB::table('legacy_outbox')->where('model', 'Diary')->count());
    }
}
