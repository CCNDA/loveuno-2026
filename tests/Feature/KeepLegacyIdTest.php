<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BetaChangeRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 熊叔叔 11/22 TG：「會員編號應該要沿用吧」→ 匯進來的會員，新版編號＝舊站編號
class KeepLegacyIdTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        BetaChangeRecorder::$importing = false;
        $this->dir = sys_get_temp_dir() . '/lu-id-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700);
    }

    protected function tearDown(): void
    {
        @unlink("{$this->dir}/export.json");
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function export(array $ids): void
    {
        file_put_contents("{$this->dir}/export.json", json_encode(['members' => array_map(fn ($lid) => [
            'legacy_id' => $lid, 'status' => 2, 'email' => "m{$lid}@example.com", 'password_hash' => password_hash('x', PASSWORD_BCRYPT),
            'nickname' => '舊站會員', 'gender' => '女', 'birth_year' => 1990, 'live_place' => '台北市', 'church' => '', 'church_visible' => false,
            'job' => '', 'intro' => '', 'photo' => null], $ids)], JSON_UNESCAPED_UNICODE));
    }

    public function test_imported_member_keeps_legacy_number(): void
    {
        $this->export([1000014, 1018068]);
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame(1000014, (int) User::where('legacy_id', 1000014)->value('id'));
        $this->assertSame(1018068, (int) User::where('legacy_id', 1018068)->value('id'));
        $this->assertSame(1000014, (int) User::find(1000014)->profile->user_id, '個人資料接在同一個編號');

        // 之後新版自己建的帳號接在最大編號後面，不會撞到
        $new = User::factory()->create();
        $this->assertGreaterThan(1018068, $new->id);

        // 再匯一次（用舊站更新）不會改編號
        config(['loveuno.beta_audit' => true]); // --update-from-legacy 要開稽核才能跑（28559e0 的守門）
        $this->artisan('legacy:import-export', ['dir' => $this->dir, '--update-from-legacy' => true])->assertSuccessful();
        $this->assertSame(1000014, (int) User::where('legacy_id', 1000014)->value('id'));
    }

    public function test_taken_number_falls_back_and_warns(): void
    {
        $other = User::factory()->create();
        \Illuminate\Support\Facades\DB::table('users')->where('id', $other->id)->update(['id' => 1000020]);
        $this->export([1000020]);
        $this->artisan('legacy:import-export', ['dir' => $this->dir])
            ->expectsOutputToContain('沒辦法沿用舊站編號的 1 位：1000020')
            ->assertSuccessful();
        $u = User::where('legacy_id', 1000020)->first();
        $this->assertNotSame(1000020, (int) $u->id);
        $this->assertNull(User::find(1000020)->legacy_id, '原本那個帳號沒被動到');
    }

    // 熊叔叔 11/22 TG：「點數也一樣，測試站需要跟舊站資料同步」
    public function test_legacy_points_carried_and_resynced(): void
    {
        $write = fn (int $coin) => file_put_contents("{$this->dir}/export.json", json_encode(['members' => [[
            'legacy_id' => 1000031, 'status' => 2, 'email' => 'm1000031@example.com', 'password_hash' => password_hash('x', PASSWORD_BCRYPT),
            'nickname' => '舊站會員', 'gender' => '女', 'birth_year' => 1990, 'live_place' => '台北市', 'church' => '', 'church_visible' => false,
            'job' => '', 'intro' => '', 'photo' => null, 'coin' => $coin]]], JSON_UNESCAPED_UNICODE));
        $write(120);
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();
        $u = User::find(1000031);
        $this->assertSame(120, (int) $u->points);
        $this->assertSame('舊站積點結轉', \Illuminate\Support\Facades\DB::table('point_transactions')->where('user_id', $u->id)->value('label'));

        // 新版自己賺的點留著
        \App\Support\Points::award($u, 'daily_login', '2026-11-22', 1, '每日登入');

        // 舊站餘額變了 → 再同步：結轉那一筆改成新餘額，總數＝舊站＋新版自己的，不會重複加
        $write(90);
        config(['loveuno.beta_audit' => true]);
        $this->artisan('legacy:import-export', ['dir' => $this->dir, '--update-from-legacy' => true])->assertSuccessful();
        $this->artisan('legacy:import-export', ['dir' => $this->dir, '--update-from-legacy' => true])->assertSuccessful();
        $this->assertSame(91, (int) $u->fresh()->points);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('point_transactions')->where('user_id', $u->id)->where('type', 'legacy_carry')->count());
    }
}
