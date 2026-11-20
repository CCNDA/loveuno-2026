<?php

namespace Tests\Feature;

use App\Http\Middleware\LogoutNotInvited;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 公測邀請制（熊叔叔 11/16 選乙「邀請名單」；Asana 1220564012971609）
class BetaInviteTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $over = []): User
    {
        $u = User::factory()->create($over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'marital' => '未婚']);

        return $u;
    }

    public function test_switch_off_everyone_can_login_as_before(): void
    {
        config(['loveuno.beta_invite_only' => false]);
        $u = $this->member();
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($u);
        $this->get('/members')->assertOk();
    }

    public function test_switch_on_not_invited_cannot_login_even_with_right_password(): void
    {
        config(['loveuno.beta_invite_only' => true]);
        $u = $this->member();
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => LogoutNotInvited::MESSAGE]);
        $this->assertGuest();
    }

    public function test_switch_on_wrong_password_does_not_reveal_invite_status(): void
    {
        config(['loveuno.beta_invite_only' => true]);
        $u = $this->member();
        $res = $this->post('/login', ['email' => $u->email, 'password' => 'wrong-password']);
        $res->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('邀請制', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_switch_on_invited_member_and_admin_can_login(): void
    {
        config(['loveuno.beta_invite_only' => true]);
        $invited = $this->member(['beta_invited_at' => now()]);
        $this->post('/login', ['email' => $invited->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($invited);
        $this->get('/members')->assertOk();
        $this->post('/logout');

        $admin = $this->member(['is_admin' => true]);
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_switch_on_already_logged_in_not_invited_is_logged_out_next_request(): void
    {
        config(['loveuno.beta_invite_only' => true]);
        $u = $this->member();
        $this->actingAs($u)->get('/members')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_revoked_member_is_logged_out_next_request(): void
    {
        config(['loveuno.beta_invite_only' => true]);
        $u = $this->member(['beta_invited_at' => now()]);
        $this->actingAs($u)->get('/members')->assertOk();
        $this->artisan('beta:invite', ['--id' => [$u->id], '--revoke' => true])->assertSuccessful();
        $this->assertNull($u->fresh()->beta_invited_at);
        $this->actingAs($u->fresh())->get('/members')->assertRedirect(route('login'));
    }

    public function test_guest_pages_still_open_when_switch_on(): void
    {
        config(['loveuno.beta_invite_only' => true]);
        $this->get('/login')->assertOk();
        $this->get('/')->assertSuccessful();
    }

    public function test_command_adds_by_id_legacy_and_file_and_is_idempotent(): void
    {
        $a = $this->member();
        $b = $this->member(['legacy_id' => 1012345]);
        $c = $this->member(['legacy_id' => 1099999]);
        $file = tempnam(sys_get_temp_dir(), 'beta');
        file_put_contents($file, "# 第一批\n{$a->id}\nL1099999\n\n");

        $this->artisan('beta:invite', ['--legacy' => [1012345]])->assertSuccessful();
        $this->artisan('beta:invite', ['--file' => $file])->assertSuccessful();
        foreach ([$a, $b, $c] as $u) {
            $this->assertNotNull($u->fresh()->beta_invited_at);
        }
        $t = $a->fresh()->beta_invited_at;
        $this->travel(5)->minutes();
        $this->artisan('beta:invite', ['--id' => [$a->id]])->assertSuccessful();
        $this->assertEquals($t, $a->fresh()->beta_invited_at, '重跑不會改掉第一次加入的時間');
        @unlink($file);
    }

    public function test_command_refuses_unknown_member_and_changes_nobody(): void
    {
        $a = $this->member();
        $this->artisan('beta:invite', ['--id' => [$a->id, 999999]])->assertFailed();
        $this->assertNull($a->fresh()->beta_invited_at);

        $file = tempnam(sys_get_temp_dir(), 'beta');
        file_put_contents($file, "{$a->id}\nalice@example.com\n");
        $this->artisan('beta:invite', ['--file' => $file])->assertFailed();
        $this->assertNull($a->fresh()->beta_invited_at);
        @unlink($file);
    }

    public function test_command_dry_run_changes_nothing_and_list_hides_email(): void
    {
        $a = $this->member(['email' => 'secret-person@example.com']);
        $this->artisan('beta:invite', ['--id' => [$a->id], '--dry-run' => true])->assertSuccessful();
        $this->assertNull($a->fresh()->beta_invited_at);
        $this->artisan('beta:invite', ['--id' => [$a->id]])->assertSuccessful();
        $this->artisan('beta:invite', ['--list' => true])
            ->expectsOutputToContain("#{$a->id}")
            ->doesntExpectOutputToContain('secret-person')
            ->assertSuccessful();
    }
}
