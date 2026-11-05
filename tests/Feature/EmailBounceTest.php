<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Notifications\NewActivity;
use App\Notifications\VerifyNewEmail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

// 退信（熊叔叔 11/5）：硬退 → 停寄通知、登入後被帶到驗證頁，可以重寄驗證信或改信箱；驗證後恢復
class EmailBounceTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $over = []): User
    {
        $u = User::factory()->create($over + ['phone_verified_at' => now()]);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_command_marks_hard_bounce_and_soft_only_logs(): void
    {
        $u = $this->member(['email' => 'bad@example.com']);
        $this->artisan('user:email-bounce', ['email' => 'bad@example.com', '--soft' => true])->assertSuccessful();
        $this->assertNull($u->fresh()->email_bounced_at);
        $this->assertNotNull($u->fresh()->email_verified_at);

        $this->artisan('user:email-bounce', ['email' => 'BAD@example.com', '--reason' => '550 user unknown'])->assertSuccessful();
        $f = $u->fresh();
        $this->assertNotNull($f->email_bounced_at);
        $this->assertSame('550 user unknown', $f->email_bounce_reason);
        $this->assertNull($f->email_verified_at);

        $this->artisan('user:email-bounce', ['email' => 'nobody@example.com'])->assertFailed();
    }

    public function test_bounced_member_gets_no_notifications_except_verification(): void
    {
        $u = $this->member(['email' => 'bad@example.com']);
        $this->assertSame('bad@example.com', $u->routeNotificationForMail(new NewActivity('新的紙條', '/notes')));
        $u->markEmailBounced('550');
        $this->assertNull($u->routeNotificationForMail(new NewActivity('新的紙條', '/notes')));
        $this->assertSame('bad@example.com', $u->routeNotificationForMail(new VerifyEmail));
    }

    public function test_bounced_member_is_sent_to_verify_page_with_both_choices(): void
    {
        $u = $this->member(['email' => 'bad@example.com']);
        $u->markEmailBounced('550');
        $this->actingAs($u)->get('/me')->assertRedirect(route('verification.notice'));
        $this->actingAs($u)->get(route('verification.notice'))->assertOk()
            ->assertSee('您的電子郵件收不到信')->assertSee('重新寄驗證信')->assertSee('改用另一個信箱')
            ->assertSee('name="new_email"', false);

        // 一般未驗證（剛註冊）的人看到的還是原本的頁面
        $fresh = $this->member(['email_verified_at' => null]);
        $this->actingAs($fresh)->get(route('verification.notice'))->assertOk()
            ->assertSee('請到信箱收驗證信')->assertDontSee('收不到信');
    }

    public function test_reverify_same_email_clears_bounce(): void
    {
        Notification::fake();
        $u = $this->member(['email' => 'bad@example.com']);
        $u->markEmailBounced('550');
        $this->actingAs($u)->post(route('verification.send'))->assertRedirect();
        Notification::assertSentTo($u, VerifyEmail::class);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $u->id, 'hash' => sha1('bad@example.com')]);
        $this->get($url)->assertRedirect(route('login'));
        $f = $u->fresh();
        $this->assertNull($f->email_bounced_at);
        $this->assertSame('', $f->email_bounce_reason);
        $this->assertNotNull($f->email_verified_at);
        $this->assertSame('bad@example.com', $f->routeNotificationForMail(new NewActivity('新的紙條', '/notes')));
    }

    public function test_bounced_member_can_change_to_new_email(): void
    {
        Notification::fake();
        $u = $this->member(['email' => 'bad@example.com']);
        $u->markEmailBounced('550');
        $this->actingAs($u)->post(route('email.change'), ['new_email' => 'good@example.com', 'current_password' => 'password'])
            ->assertRedirect(route('verification.notice'));
        $url = null;
        Notification::assertSentTo(new AnonymousNotifiable, VerifyNewEmail::class, function ($n, $c, $to) use (&$url) {
            $url = $n->toMail($to)->actionUrl;

            return $to->routes['mail'] === 'good@example.com';
        });
        $this->actingAs($u)->get($url)->assertRedirect();
        $f = $u->fresh();
        $this->assertSame('good@example.com', $f->email);
        $this->assertNull($f->email_bounced_at);
        $this->assertNotNull($f->email_verified_at);
        $this->actingAs($f)->get('/me')->assertRedirect(route('members.show', $f)); // 過了 email 驗證（/me 會轉到自己的檔案，不再被帶到驗證頁）
    }

    public function test_change_email_still_needs_password_for_bounced_member(): void
    {
        Notification::fake();
        $u = $this->member(['email' => 'bad@example.com']);
        $u->markEmailBounced('550');
        $this->actingAs($u)->post(route('email.change'), ['new_email' => 'good@example.com', 'current_password' => 'wrong'])
            ->assertSessionHasErrorsIn('email', 'current_password');
        Notification::assertNothingSent();
        $this->post(route('logout'));
        $this->post(route('email.change'), ['new_email' => 'x@example.com', 'current_password' => 'password'])->assertRedirect(route('login'));
    }

    public function test_admin_member_page_shows_bounce(): void
    {
        $admin = $this->member(['is_admin' => true]);
        $u = $this->member(['email' => 'bad@example.com']);
        $u->markEmailBounced('550 user unknown');
        $this->actingAs($admin)->get(route('admin.members.show', $u))->assertOk()
            ->assertSee('信箱無效')->assertSee('550 user unknown');
    }
}
