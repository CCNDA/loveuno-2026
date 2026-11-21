<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\Friendship;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

// 小布 11/21 第二批審查抓到的漏洞（Asana 1220564106252789）：每一項一個測試，並做陰性對照
class BetaReviewGapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['loveuno.beta_audit' => true]);
    }

    private function member(array $over = []): User
    {
        $u = User::factory()->create($over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'marital' => '未婚']);

        return $u;
    }

    private function outbox(string $model, string $action): int
    {
        return DB::table('legacy_outbox')->where('model', $model)->where('action', $action)->count();
    }

    // 🔴1 解除好友（封鎖也走這裡）要排回寫
    public function test_friendship_remove_goes_to_outbox(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        Friendship::raise($a, $b, 1);
        DB::table('legacy_outbox')->delete();
        Friendship::remove($a, $b);
        $this->assertSame(1, $this->outbox('Friendship', 'deleted'));
    }

    // 🔴1 升等要排回寫，payload 是新等級
    public function test_friendship_raise_level_goes_to_outbox(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        Friendship::raise($a, $b, 1);
        DB::table('legacy_outbox')->delete();
        Friendship::raise($a, $b, 2);
        $row = DB::table('legacy_outbox')->where('model', 'Friendship')->where('action', 'updated')->first();
        $this->assertNotNull($row);
        $this->assertSame(2, (int) json_decode($row->payload, true)['level']);
        $this->assertSame(2, (int) Friendship::between($a, $b)->level);
        // 同一等級再升一次：沒變就不排
        Friendship::raise($a, $b, 2);
        $this->assertSame(1, $this->outbox('Friendship', 'updated'));
    }

    // 🔴2（暫時）註銷帳號：記 audit，不排回寫（不把舊站暱稱改成「已註銷會員」）
    public function test_account_deletion_rename_not_sent_to_legacy(): void
    {
        $u = $this->member(['legacy_id' => 1000901]);
        DB::table('legacy_outbox')->delete();
        $u->forceFill(['name' => '已註銷會員', 'deleted_at' => now(), 'is_active' => false])->save();
        $this->assertSame(0, $this->outbox('User', 'updated'));
        $this->assertSame(1, DB::table('beta_audits')->where('table_name', 'users')->where('record_id', (string) $u->id)->where('action', 'updated')->count());
        // 一般改暱稱照樣排
        $v = $this->member(['legacy_id' => 1000902]);
        $v->forceFill(['name' => '新暱稱'])->save();
        $this->assertSame(1, $this->outbox('User', 'updated'));
    }

    // 🟡6 寫入紀錄只留 N 天
    public function test_prune_audits_keeps_recent_only(): void
    {
        config(['loveuno.beta_audit_keep_days' => 60]);
        DB::table('beta_audits')->insert([
            ['actor' => 'console', 'table_name' => 'x', 'record_id' => '1', 'action' => 'created', 'before' => null, 'after' => '{}', 'created_at' => now()->subDays(61)],
            ['actor' => 'console', 'table_name' => 'x', 'record_id' => '2', 'action' => 'created', 'before' => null, 'after' => '{}', 'created_at' => now()->subDays(59)],
        ]);
        $this->artisan('beta:prune-audits')->assertSuccessful();
        $this->assertSame(['2'], DB::table('beta_audits')->where('table_name', 'x')->pluck('record_id')->all());
    }

    // 🟠5 停寫時：驗證信連結（GET）不寫入
    public function test_readonly_blocks_email_verification_link(): void
    {
        config(['loveuno.beta_readonly' => true]);
        $u = $this->member(['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $u->id, 'hash' => sha1($u->getEmailForVerification())]);
        $this->actingAs($u)->get($url);
        $this->assertNull($u->fresh()->email_verified_at);
    }

    // 🟠5 停寫時：每日登入不給點
    public function test_readonly_no_daily_login_points(): void
    {
        $u = $this->member(['email_verified_at' => now(), 'phone_verified_at' => now(), 'points' => 0]);
        config(['loveuno.beta_readonly' => true]);
        $this->actingAs($u)->get('/');
        $this->assertSame(0, (int) $u->fresh()->points);
        config(['loveuno.beta_readonly' => false]);
        $this->actingAs($u)->get('/');
        $this->assertSame(1, (int) $u->fresh()->points, '開關關著時照常給點（對照）');
    }
}
