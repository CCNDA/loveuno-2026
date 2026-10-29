<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CenterTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $u = User::factory()->create(['password' => 'old-password-1']);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'live_place' => '台北市']);

        return $u;
    }

    public function test_center_tabs_and_current_marks(): void
    {
        $u = $this->member();
        $this->actingAs($u)->get('/center')->assertRedirect('/center/account');
        $html = $this->actingAs($u)->get('/center/account')->assertOk()->assertSee('會員中心')->getContent();
        $this->assertMatchesRegularExpression('#aria-current="page"\s*>帳號管理#u', $html);
        $this->actingAs($u)->get('/me/edit')->assertOk()->assertSee('個人資料與自我介紹');
        $this->actingAs($u)->get('/me/photo')->assertOk()->assertSee('身份認證（年底開放前）');
        $this->actingAs($u)->get('/center/points')->assertOk()->assertSee('還在準備中');
    }

    public function test_change_password_requires_current_and_rehashes(): void
    {
        $u = $this->member();
        $this->actingAs($u)->put('/center/password', ['current_password' => 'wrong', 'password' => 'new-password-22', 'password_confirmation' => 'new-password-22'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($u)->put('/center/password', ['current_password' => 'old-password-1', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->actingAs($u)->put('/center/password', ['current_password' => 'old-password-1', 'password' => 'new-password-22', 'password_confirmation' => 'new-password-22'])
            ->assertRedirect('/center/account')->assertSessionHas('status');
        $this->assertTrue(Hash::check('new-password-22', $u->fresh()->password));
    }

    public function test_preferences_save_and_validate(): void
    {
        $u = $this->member();
        $this->actingAs($u)->get('/center/preferences')->assertOk()->assertSee('交友條件');
        $this->actingAs($u)->put('/center/preferences', ['pref_gender' => '男', 'pref_age_min' => 30, 'pref_age_max' => 40, 'pref_place' => '台北市'])
            ->assertRedirect('/center/preferences');
        $p = $u->profile->fresh();
        $this->assertSame(['男', 30, 40, '台北市'], [$p->pref_gender, (int) $p->pref_age_min, (int) $p->pref_age_max, $p->pref_place]);

        $this->actingAs($u)->put('/center/preferences', ['pref_age_min' => 40, 'pref_age_max' => 30])->assertSessionHasErrors('pref_age_max');
        $this->actingAs($u)->put('/center/preferences', ['pref_age_min' => 10])->assertSessionHasErrors('pref_age_min');
        $this->actingAs($u)->put('/center/preferences', ['pref_gender' => 'x'])->assertSessionHasErrors('pref_gender');

        // 全部清空＝不限
        $this->actingAs($u)->put('/center/preferences', [])->assertRedirect('/center/preferences');
        $p = $u->profile->fresh();
        $this->assertSame(['', null, null, ''], [$p->pref_gender, $p->pref_age_min, $p->pref_age_max, $p->pref_place]);
    }

    public function test_guest_and_unverified_cannot_enter(): void
    {
        $this->get('/center/account')->assertRedirect('/login');
        $this->put('/center/password', [])->assertRedirect('/login');
        $pending = User::factory()->unverified()->create();
        $this->actingAs($pending)->get('/center/account')->assertRedirect('/email/verify');
    }
}
