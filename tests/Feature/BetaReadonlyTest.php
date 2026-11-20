<?php

namespace Tests\Feature;

use App\Http\Middleware\BetaReadonly;
use App\Models\Diary;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 公測停寫開關（熊叔叔 11/17 TG 選甲；Asana 1220586286066431）
class BetaReadonlyTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $over = []): User
    {
        $u = User::factory()->create($over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'marital' => '未婚']);

        return $u;
    }

    private function diaryForm(): array
    {
        return ['title' => '停寫測試', 'body' => '內容', 'visibility' => 'public'];
    }

    public function test_switch_off_writes_work_as_before(): void
    {
        config(['loveuno.beta_readonly' => false]);
        $u = $this->member();
        $this->actingAs($u)->post('/diaries', $this->diaryForm())->assertRedirect();
        $this->assertSame(1, Diary::where('user_id', $u->id)->count());
    }

    public function test_switch_on_blocks_writes_and_says_why(): void
    {
        config(['loveuno.beta_readonly' => true]);
        $u = $this->member();
        $this->actingAs($u)->from('/members')->post('/diaries', $this->diaryForm())
            ->assertRedirect('/members')
            ->assertSessionHas('status', BetaReadonly::MESSAGE);
        $this->assertSame(0, Diary::where('user_id', $u->id)->count());
    }

    public function test_switch_on_json_write_gets_503(): void
    {
        config(['loveuno.beta_readonly' => true]);
        $u = $this->member();
        $this->actingAs($u)->postJson('/diaries', $this->diaryForm())->assertStatus(503)->assertJson(['message' => BetaReadonly::MESSAGE]);
        $this->assertSame(0, Diary::where('user_id', $u->id)->count());
    }

    public function test_switch_on_reading_login_and_logout_still_work(): void
    {
        config(['loveuno.beta_readonly' => true]);
        $u = $this->member();
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($u);
        $this->get('/members')->assertOk();
        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_switch_on_blocks_every_write_method(): void
    {
        config(['loveuno.beta_readonly' => true]);
        $u = $this->member();
        // 都是真的存在的路由（不存在的會先 404，測不到開關）
        $this->actingAs($u)->putJson('/center/settings', ['name' => '改名'])->assertStatus(503);
        $this->actingAs($u)->patchJson('/center/settings/fields', ['name' => '改名'])->assertStatus(503);
        $this->actingAs($u)->deleteJson('/center/account', ['password' => 'password'])->assertStatus(503);
        $this->assertNotSame('改名', $u->fresh()->name);
        $this->assertNotNull(User::find($u->id), '帳號沒被刪');
    }
}
