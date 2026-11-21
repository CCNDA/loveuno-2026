<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 熊叔叔 11/22 TG 選丙：公測期間先關掉新版的註銷（config loveuno.account_delete_enabled）
class AccountDeleteSwitchTest extends TestCase
{
    use RefreshDatabase;

    private string $reason = '測試註銷的原因至少十個字以上喔';

    public function test_switched_off_blocks_page_and_delete_and_hides_button(): void
    {
        config(['loveuno.account_delete_enabled' => false]);
        $u = User::factory()->create();
        $this->actingAs($u)->get('/center/delete')->assertRedirect(route('center.account'))->assertSessionHas('status');
        $this->actingAs($u)->delete('/center/account', ['current_password' => 'password', 'confirm' => '1', 'reason' => $this->reason])
            ->assertRedirect(route('center.account'));
        $this->assertNull($u->fresh()->deleted_at, '沒有被註銷');
        $this->assertSame($u->email, $u->fresh()->email);
        $this->actingAs($u)->get('/center/account')->assertOk()->assertDontSee(route('settings.delete'), false);
    }

    public function test_switched_on_works_as_before(): void
    {
        config(['loveuno.account_delete_enabled' => true]);
        $u = User::factory()->create();
        $this->actingAs($u)->get('/center/account')->assertOk()->assertSee(route('settings.delete'), false);
        $this->actingAs($u)->get('/center/delete')->assertOk();
        $this->actingAs($u)->delete('/center/account', ['current_password' => 'password', 'confirm' => '1', 'reason' => $this->reason])->assertRedirect('/');
        $this->assertNotNull($u->fresh()->deleted_at);
    }
}
