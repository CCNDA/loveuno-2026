<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get('/login')->assertSee(route('password.request'), false)->assertSee('忘記密碼');
        $this->get('/forgot-password')->assertOk()->assertSee('寄重設密碼的信');
    }

    public function test_same_message_whether_or_not_member_exists(): void
    {
        Notification::fake();
        $u = User::factory()->create(['email' => 'member@example.com']);

        $a = $this->post('/forgot-password', ['email' => 'Member@Example.com']);
        $b = $this->post('/forgot-password', ['email' => 'nobody@example.com']);
        $a->assertRedirect('/forgot-password');
        $b->assertRedirect('/forgot-password');
        $this->assertSame($a->getSession()->get('status'), $b->getSession()->get('status'));

        Notification::assertSentTo($u, ResetPassword::class, function ($n) use ($u) {
            $mail = $n->toMail($u);
            $this->assertSame('重設密碼｜愛諾園', $mail->subject);
            $this->assertStringContainsString('/reset-password/', $mail->actionUrl);

            return true;
        });
        Notification::assertCount(1);
    }

    public function test_reset_with_valid_token_changes_password_and_verifies_email(): void
    {
        $u = User::factory()->unverified()->create(['email' => 'member@example.com', 'remember_token' => 'old']);
        $token = Password::createToken($u);

        $this->get('/reset-password/' . $token . '?email=member@example.com')
            ->assertOk()->assertSee('value="member@example.com"', false);
        $this->post('/reset-password', [
            'token' => $token, 'email' => 'member@example.com',
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/login');

        $u->refresh();
        $this->assertTrue(Hash::check('brand-new-pass', $u->password));
        $this->assertNotSame('old', $u->remember_token);
        $this->assertNotNull($u->email_verified_at);

        // 同一個連結不能用第二次
        $this->post('/reset-password', [
            'token' => $token, 'email' => 'member@example.com',
            'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('brand-new-pass', $u->fresh()->password));
    }

    public function test_bad_token_short_password_and_wrong_email_rejected(): void
    {
        $u = User::factory()->create(['email' => 'member@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $token = Password::createToken($u);
        $old = $u->password;
        $form = fn (array $over) => array_merge([
            'token' => $token, 'email' => 'member@example.com',
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ], $over);

        $this->post('/reset-password', $form(['token' => 'nope']))->assertSessionHasErrors('email');
        $this->post('/reset-password', $form(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        // 拿 A 的連結去改 B 的密碼
        $this->post('/reset-password', $form(['email' => 'other@example.com']))->assertSessionHasErrors('email');

        $this->assertSame($old, $u->fresh()->password);
        $this->assertSame($other->password, $other->fresh()->password);
    }

    public function test_logged_in_member_is_sent_away_and_requests_are_throttled(): void
    {
        $this->actingAs(User::factory()->create())->get('/forgot-password')->assertRedirect();
        auth()->logout();

        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "x{$i}@example.com"])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'x9@example.com'])->assertStatus(429);
    }
}
