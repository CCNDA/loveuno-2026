<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\Profile;
use App\Models\User;
use App\Support\BetaChangeRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// 公測增量同步（舊→新）：legacy:import-content --update-from-legacy（Asana 1220564106252789）
class ContentIncrementalImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['loveuno.beta_audit' => true]);
        BetaChangeRecorder::$importing = false;
        $this->dir = sys_get_temp_dir() . '/lu-cinc-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*") ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function member(int $lid): User
    {
        $u = User::factory()->create(['legacy_id' => $lid]);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'marital' => '未婚']);

        return $u;
    }

    private function migrated(User $u, int $lid, string $title = '原標題'): Diary
    {
        return Diary::forceCreate(['user_id' => $u->id, 'title' => $title, 'body' => '原內容', 'visibility' => 'public',
            'published_on' => '2026-11-01', 'legacy_source' => 'diary', 'legacy_id' => $lid]);
    }

    private function export(array $diaries, ?array $owners): void
    {
        file_put_contents("{$this->dir}/content_manifest.json", json_encode(['since' => $owners === null ? null : '2026-11-21 00:00:00',
            'diary_owners' => $owners, 'kinds' => ['diary'], 'counts' => []]));
        file_put_contents("{$this->dir}/diary.jsonl", implode('', array_map(fn ($d) => json_encode($d + ['date' => '2026-11-01', 'status' => 0], JSON_UNESCAPED_UNICODE) . "\n", $diaries)));
    }

    private function run_(bool $flag = true): void
    {
        $this->artisan('legacy:import-content', ['dir' => $this->dir] + ($flag ? ['--update-from-legacy' => true] : []))->assertSuccessful();
    }

    public function test_changed_in_legacy_updates_and_hidden_becomes_private(): void
    {
        $u = $this->member(1000801);
        $a = $this->migrated($u, 11);
        $b = $this->migrated($u, 12);
        $this->export([
            ['legacy_id' => 11, 'owner' => 1000801, 'title' => '舊站改了標題', 'body' => '舊站改了內容'],
            ['legacy_id' => 12, 'owner' => 1000801, 'title' => '原標題', 'body' => '原內容', 'status' => -1],
        ], [1000801]);
        DB::table("legacy_outbox")->delete(); // 上面建測試資料時排的
        $this->run_();
        $this->assertSame('舊站改了標題', $a->fresh()->title);
        $this->assertSame('舊站改了內容', $a->fresh()->body);
        $this->assertSame('private', $b->fresh()->visibility);
        $this->assertSame(0, DB::table('legacy_outbox')->count(), '從舊站來的不排回寫');
    }

    public function test_member_edit_in_new_site_wins(): void
    {
        $u = $this->member(1000802);
        $d = $this->migrated($u, 21);
        $this->actingAs($u);
        $d->update(['title' => '新版改的']);
        auth()->logout();
        $this->export([['legacy_id' => 21, 'owner' => 1000802, 'title' => '舊站改的', 'body' => '原內容']], [1000802]);
        $this->run_();
        $this->assertSame('新版改的', $d->fresh()->title);
    }

    public function test_deleted_in_legacy_deleted_only_for_covered_authors(): void
    {
        $covered = $this->member(1000803);
        $other = $this->member(1000804);
        $keep = $this->migrated($covered, 31);
        $gone = $this->migrated($covered, 32);
        $newSiteOwn = Diary::forceCreate(['user_id' => $covered->id, 'title' => '新版自己寫的', 'body' => 'x', 'visibility' => 'public', 'published_on' => '2026-11-21']);
        $otherDiary = $this->migrated($other, 41);
        $this->export([
            ['legacy_id' => 31, 'owner' => 1000803, 'title' => '原標題', 'body' => '原內容'],
            ['legacy_id' => 33, 'owner' => 1000803, 'title' => '舊站剛寫的', 'body' => '新的一篇'],
        ], [1000803]);
        $this->run_();
        $this->assertNotNull($keep->fresh());
        $this->assertSame(1, Diary::where('legacy_source', 'diary')->where('legacy_id', 33)->count(), '這一輪新匯進來的不能當場刪掉（小布 🔴1）');
        $this->assertNull($gone->fresh(), '涵蓋的作者、舊站已刪 → 新版也刪');
        $this->assertNotNull($newSiteOwn->fresh(), '新版自己寫的不刪');
        $this->assertNotNull($otherDiary->fresh(), '這次沒涵蓋的作者不動');
    }

    // 小布 🟠2：會員在新版刪掉的搬來日記，舊站還在（回寫只設隱藏）→ 下一輪不能再匯回來
    public function test_diary_deleted_in_new_site_not_reimported(): void
    {
        $u = $this->member(1000807);
        $d = $this->migrated($u, 71);
        $this->actingAs($u);
        $d->delete();
        auth()->logout();
        $this->export([['legacy_id' => 71, 'owner' => 1000807, 'title' => '原標題', 'body' => '原內容', 'status' => -1]], [1000807]);
        $this->run_();
        $this->assertSame(0, Diary::where('legacy_source', 'diary')->where('legacy_id', 71)->count());
    }

    public function test_mass_delete_guard(): void
    {
        $u = $this->member(1000805);
        for ($i = 1; $i <= 51; $i++) {
            $this->migrated($u, 500 + $i);
        }
        $this->export([], [1000805]);
        $this->run_();
        $this->assertSame(51, Diary::where('user_id', $u->id)->count(), '一次超過 50 篇不刪');
    }

    public function test_without_flag_nothing_updated_or_deleted(): void
    {
        $u = $this->member(1000806);
        $a = $this->migrated($u, 61);
        $b = $this->migrated($u, 62);
        $this->export([['legacy_id' => 61, 'owner' => 1000806, 'title' => '舊站改了', 'body' => '原內容']], [1000806]);
        $this->run_(false);
        $this->assertSame('原標題', $a->fresh()->title);
        $this->assertNotNull($b->fresh());
    }
}
