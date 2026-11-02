<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// 後台：會員搜尋與管理（上線前檢測表 D-02；熊叔叔 11/2 TG：只做停權、垃圾帳號用停權寫原因）
class AdminMemberTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'marital' => '未婚']);

        return $u;
    }

    private function admin(): User
    {
        return $this->member('管理員', ['is_admin' => true]);
    }

    public function test_only_admin_can_open(): void
    {
        $m = $this->member();
        $this->actingAs($m)->get('/admin/members')->assertForbidden();
        $this->actingAs($m)->get("/admin/members/{$m->id}")->assertForbidden();
        $this->actingAs($m)->post("/admin/members/{$m->id}/suspend", ['reason' => '垃圾帳號'])->assertForbidden();
        $this->assertNull($m->fresh()->suspended_at);

        $this->actingAs($this->admin())->get('/admin/members')->assertOk()->assertSee('會員管理')->assertSee(route('admin.members'), false);
    }

    public function test_search_by_id_legacy_id_email_name_phone(): void
    {
        $admin = $this->admin();
        $a = $this->member('甲小芯', ['email' => 'alpha@example.com', 'legacy_id' => 1012345, 'phone' => '0912345678']);
        $b = $this->member('乙會員', ['email' => 'beta@example.com']);

        foreach ([(string) $a->id, '1012345', 'alpha@', '小芯', '0912345'] as $q) {
            $this->actingAs($admin)->get('/admin/members?q=' . urlencode($q))->assertOk()
                ->assertSee('甲小芯')->assertDontSee('乙會員');
        }
        // % 和 _ 是一般字元，不是萬用字元
        $this->actingAs($admin)->get('/admin/members?q=' . urlencode('%'))->assertOk()->assertSee('找不到符合的會員');
    }

    public function test_suspend_hides_member_and_blocks_login(): void
    {
        $admin = $this->admin();
        $bad = $this->member('可疑的人');
        $other = $this->member('其他會員');

        $this->actingAs($admin)->post("/admin/members/{$bad->id}/suspend", ['reason' => ''])
            ->assertSessionHasErrors('reason', null, 'suspend');
        $this->actingAs($admin)->post("/admin/members/{$bad->id}/suspend", ['reason' => '垃圾帳號'])
            ->assertRedirect(route('admin.members.show', $bad));

        $bad->refresh();
        $this->assertNotNull($bad->suspended_at);
        $this->assertSame('垃圾帳號', $bad->suspended_reason);
        $this->assertFalse($bad->is_active);
        $this->assertTrue($bad->suspend_prev_active);
        $this->assertDatabaseHas('admin_actions', ['admin_id' => $admin->id, 'user_id' => $bad->id, 'action' => 'suspend']);

        // 別人看不到；管理員看得到
        $this->actingAs($other)->get("/members/{$bad->id}")->assertNotFound();
        $this->actingAs($admin)->get("/members/{$bad->id}")->assertOk();
        // 列表篩選「停權中」看得到他
        $this->actingAs($admin)->get('/admin/members?filter=suspended')->assertSee('可疑的人')->assertDontSee('其他會員');

        // 已經登入的：下一個請求就被登出
        $this->actingAs($bad)->get('/diaries')->assertRedirect(route('login'));
        $this->assertGuest();

        // 登入頁：密碼對也不能登入
        $this->post('/login', ['email' => $bad->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unsuspend_restores_previous_active_setting(): void
    {
        $admin = $this->admin();
        $a = $this->member('原本啟用');
        $b = $this->member('原本自己暫停', ['is_active' => false]);
        foreach ([$a, $b] as $u) {
            $this->actingAs($admin)->post("/admin/members/{$u->id}/suspend", ['reason' => '騷擾其他會員']);
            $this->actingAs($admin)->post("/admin/members/{$u->id}/unsuspend")->assertRedirect(route('admin.members.show', $u));
        }
        $this->assertNull($a->fresh()->suspended_at);
        $this->assertTrue($a->fresh()->is_active);
        $this->assertNull($b->fresh()->suspended_at);
        $this->assertFalse($b->fresh()->is_active); // 會員自己關的，解除停權不幫他打開
        $this->assertSame(2, AdminAction::where('action', 'unsuspend')->count());

        // 解除後可以登入（先把管理員登出，不然 /login 會直接轉走）
        auth()->logout();
        $this->post('/login', ['email' => $a->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($a);
    }

    public function test_cannot_suspend_admin_or_deleted(): void
    {
        $admin = $this->admin();
        $other = $this->member('另一位管理員', ['is_admin' => true]);
        $gone = $this->member('已註銷會員', ['deleted_at' => now(), 'is_active' => false]);
        $this->actingAs($admin)->post("/admin/members/{$other->id}/suspend", ['reason' => '垃圾帳號'])->assertStatus(422);
        $this->actingAs($admin)->post("/admin/members/{$gone->id}/suspend", ['reason' => '垃圾帳號'])->assertStatus(422);
        $this->assertNull($other->fresh()->suspended_at);
        $this->assertNull($gone->fresh()->suspended_at);
    }

    public function test_admin_can_set_married_but_members_cannot(): void
    {
        $admin = $this->admin();
        $m = $this->member('要結婚的人');
        $this->actingAs($admin)->post("/admin/members/{$m->id}/marital", ['marital' => '已婚', 'note' => '見證通過'])
            ->assertRedirect(route('admin.members.show', $m));
        $this->assertSame('已婚', $m->profile->fresh()->marital);
        $this->assertDatabaseHas('admin_actions', ['user_id' => $m->id, 'action' => 'marital']);
        $this->actingAs($admin)->post("/admin/members/{$m->id}/marital", ['marital' => '亂填'])->assertSessionHasErrors('marital', null, 'marital');
        $this->assertSame('已婚', $m->profile->fresh()->marital);

        // 會員自己的選單沒有「已婚」
        $this->assertNotContains('已婚', \App\Support\ProfileOptions::MARITAL);
    }

    public function test_points_adjust_with_reason(): void
    {
        $admin = $this->admin();
        $m = $this->member('拿點數的人');

        $this->actingAs($admin)->post("/admin/members/{$m->id}/points", ['amount' => 15, 'reason' => '活動補發'])->assertRedirect();
        $this->assertSame(15, $m->fresh()->points);
        $this->assertDatabaseHas('point_transactions', ['user_id' => $m->id, 'type' => 'admin', 'amount' => 15, 'label' => '管理員調整：活動補發']);

        $this->actingAs($admin)->post("/admin/members/{$m->id}/points", ['amount' => -5, 'reason' => '更正'])->assertRedirect();
        $this->assertSame(10, $m->fresh()->points);

        // 扣到變負的：不扣
        $this->actingAs($admin)->post("/admin/members/{$m->id}/points", ['amount' => -11, 'reason' => '更正'])->assertSessionHasErrors('amount', null, 'points');
        $this->assertSame(10, $m->fresh()->points);
        // 0、沒有原因、超過 1000：不收
        $this->actingAs($admin)->post("/admin/members/{$m->id}/points", ['amount' => 0, 'reason' => '更正'])->assertSessionHasErrors('amount', null, 'points');
        $this->actingAs($admin)->post("/admin/members/{$m->id}/points", ['amount' => 5, 'reason' => ''])->assertSessionHasErrors('reason', null, 'points');
        $this->actingAs($admin)->post("/admin/members/{$m->id}/points", ['amount' => 1001, 'reason' => '太多'])->assertSessionHasErrors('amount', null, 'points');
        $this->assertSame(10, $m->fresh()->points);
        $this->assertSame(2, DB::table('point_transactions')->where('user_id', $m->id)->where('type', 'admin')->count());
        $this->assertSame(2, AdminAction::where('user_id', $m->id)->where('action', 'points')->count());

        // 會員自己的積點紀錄看得到原因
        $this->actingAs($m)->get(route('center.points', ['tab' => 'records']))->assertSee('管理員調整：活動補發');
    }

    public function test_rename_and_log_shown_on_member_page(): void
    {
        $admin = $this->admin();
        $m = $this->member("O'Brien");
        $this->actingAs($admin)->post("/admin/members/{$m->id}/rename", ['name' => str_repeat('長', 21)])->assertSessionHasErrors('name', null, 'rename');
        $this->actingAs($admin)->post("/admin/members/{$m->id}/rename", ['name' => '新暱稱'])->assertRedirect(route('admin.members.show', $m));
        $this->assertSame('新暱稱', $m->fresh()->name);

        $this->actingAs($admin)->get("/admin/members/{$m->id}")->assertOk()
            ->assertSee('管理紀錄')->assertSee('改暱稱')->assertSee("O'Brien → 新暱稱")
            ->assertSee('婚姻狀況')->assertSee('加減愛諾積點');
    }

    public function test_nickname_never_goes_into_confirm_script(): void
    {
        $admin = $this->admin();
        $m = $this->member("x');alert(1);//");
        $html = $this->actingAs($admin)->get("/admin/members/{$m->id}")->assertOk()->getContent();
        // confirm() 裡只有固定文字
        $this->assertSame(0, preg_match('/confirm\([^)]*alert/', $html));
        $this->assertStringContainsString("confirm('確定要停權這位會員嗎？')", $html);
    }
}
