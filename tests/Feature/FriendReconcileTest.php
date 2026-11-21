<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\Profile;
use App\Models\User;
use App\Support\BetaChangeRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// 好友每天全量對帳：legacy:import-content --update-from-legacy（全量匯出時；Asana 1220564106252789）
class FriendReconcileTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['loveuno.beta_audit' => true]);
        BetaChangeRecorder::$importing = false;
        $this->dir = sys_get_temp_dir() . '/lu-frec-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*") ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function member(?int $lid): User
    {
        $u = User::factory()->create(['legacy_id' => $lid]);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'marital' => '未婚']);

        return $u;
    }

    private function export(array $pairs, ?string $since = null): void
    {
        file_put_contents("{$this->dir}/content_manifest.json", json_encode(['since' => $since, 'kinds' => ['friend'], 'counts' => []]));
        file_put_contents("{$this->dir}/friend.jsonl", implode('', array_map(fn ($p) => json_encode(['legacy_id' => $p[0], 'a' => $p[1], 'b' => $p[2], 'level' => $p[3]]) . "\n", $pairs)));
    }

    private function run_(): void
    {
        $this->artisan('legacy:import-content', ['dir' => $this->dir, '--update-from-legacy' => true])->assertSuccessful();
    }

    public function test_full_export_reconciles_levels_and_removals(): void
    {
        [$a, $b, $c, $d] = [$this->member(1000901), $this->member(1000902), $this->member(1000903), $this->member(1000904)];
        $newOnly = $this->member(null);
        Friendship::raise($a, $b, 1);   // 舊站升成 2
        Friendship::raise($a, $c, 1);   // 舊站已解除
        Friendship::raise($a, $newOnly, 1); // 一方是新版自己開的：不動
        $this->actingAs($d);
        Friendship::raise($a, $d, 2);   // 會員在新版加的：舊站沒有也不動
        auth()->logout();
        DB::table('legacy_outbox')->delete();

        $this->export([[1, 1000901, 1000902, 2]]);
        $this->run_();
        $this->assertSame(2, (int) Friendship::between($a, $b)->level);
        $this->assertNull(Friendship::between($a, $c));
        $this->assertNotNull(Friendship::between($a, $newOnly));
        $this->assertNotNull(Friendship::between($a, $d));
        $this->assertSame(0, DB::table('legacy_outbox')->count(), '從舊站來的不排回寫');
    }

    // 小布 🟠2：會員在新版解除（或封鎖）了，回寫還沒送到舊站 → 全量對帳不能把好友加回來
    public function test_removed_in_new_site_not_added_back(): void
    {
        [$a, $b] = [$this->member(1000921), $this->member(1000922)];
        Friendship::raise($a, $b, 1);
        $this->actingAs($a);
        Friendship::remove($a, $b);
        auth()->logout();
        $this->export([[1, 1000921, 1000922, 1]]);
        $this->run_();
        $this->assertNull(Friendship::between($a, $b));
    }

    public function test_incremental_export_does_not_reconcile(): void
    {
        [$a, $c] = [$this->member(1000911), $this->member(1000913)];
        Friendship::raise($a, $c, 1);
        $this->export([], '2026-11-21 00:00:00');
        $this->run_();
        $this->assertNotNull(Friendship::between($a, $c), '增量匯出沒有完整好友名單，不能拿來解除');
    }

    public function test_refuses_without_audit(): void
    {
        config(['loveuno.beta_audit' => false]);
        $this->export([]);
        $this->artisan('legacy:import-content', ['dir' => $this->dir, '--update-from-legacy' => true])->assertFailed();
        $this->artisan('legacy:import-export', ['dir' => $this->dir, '--update-from-legacy' => true])->assertFailed();
    }
}
