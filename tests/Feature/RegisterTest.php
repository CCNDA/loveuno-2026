<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Notifications\RegisterAttemptOnExistingAccount;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        return array_merge([
            'email' => 'new+church@example.com',
            'password' => 'long-enough-pw',
            'password_confirmation' => 'long-enough-pw',
            'name' => '新朋友',
            'gender' => '女',
            'birth_year' => (int) now()->format('Y') - 30,
            'agree' => '1',
        ], $over);
    }

    private function verifyUrl(User $user, ?string $email = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($email ?? $user->getEmailForVerification())]);
    }

    public function test_registration_is_closed_by_default(): void
    {
        // 不改設定，看預設值（phpunit.xml／.env.example 都沒有打開）
        $this->assertFalse(config('loveuno.registration_open'));
        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->form())->assertNotFound();
        $this->get('/register/sent')->assertNotFound();
        // 已登入的人也是 404（不是被 guest 轉走）
        $this->actingAs(User::factory()->create())->get('/register')->assertNotFound();
        $this->assertSame(1, User::count());
    }

    public function test_register_sends_verification_and_hashes_password(): void
    {
        config(['loveuno.registration_open' => true]);
        Notification::fake();

        $this->get('/register')->assertOk()->assertSee('加入會員');
        $this->post('/register', $this->form(['email' => 'New+Church@Example.com']))->assertRedirect('/register/sent');

        $user = User::where('email', 'new+church@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->is_demo);
        $this->assertTrue(Hash::check('long-enough-pw', $user->password));
        $this->assertSame('女', $user->profile->gender);
        $this->assertGuest(); // 不自動登入
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_existing_email_gets_same_page_and_a_notice_not_an_account(): void
    {
        config(['loveuno.registration_open' => true]);
        Notification::fake();
        $old = User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', $this->form(['email' => 'TAKEN@example.com']))
            ->assertRedirect('/register/sent')->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
        Notification::assertSentTo($old, RegisterAttemptOnExistingAccount::class);
        Notification::assertNotSentTo($old, VerifyEmail::class);
        $this->assertGuest();
    }

    public function test_unverified_member_cannot_see_member_pages(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/diaries')->assertRedirect('/email/verify');
        $this->actingAs($user)->get('/members')->assertRedirect('/email/verify');
    }

    public function test_verification_link_verifies_without_login(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verifyUrl($user))->assertRedirect('/login');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_or_foreign_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        // hash 對不上
        $this->get($this->verifyUrl($user, 'someone-else@example.com'))->assertForbidden();
        // 拿別人的 hash 配自己的 id
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($other->getEmailForVerification())]))->assertForbidden();
        // 簽章被改（換 id）
        $this->get(str_replace("/verify/{$user->id}/", "/verify/{$other->id}/", $this->verifyUrl($user)))->assertForbidden();
        // 過期
        $this->get(URL::temporarySignedRoute('verification.verify', now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]))->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_resend_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->post('/email/verification-notification')->assertRedirect();
        }
        $this->actingAs($user)->post('/email/verification-notification')->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    public function test_member_list_hides_unverified_members(): void
    {
        $viewer = User::factory()->create(['name' => '看的人']);
        Profile::create(['user_id' => $viewer->id, 'gender' => '男']);
        $pending = User::factory()->unverified()->create(['name' => '還沒驗證的人']);
        Profile::create(['user_id' => $pending->id, 'gender' => '女']);

        $this->actingAs($viewer)->get('/members')->assertOk()->assertDontSee('還沒驗證的人')->assertSee('共 1 位');
    }

    public function test_validation_rules(): void
    {
        config(['loveuno.registration_open' => true]);
        // 這裡要送 6 次錯的表單，會撞到一小時 5 次的限流（限流另有一項測試）
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $tooYoung = (int) now()->format('Y') - 17;

        $this->post('/register', $this->form(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->post('/register', $this->form(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', $this->form(['password_confirmation' => 'different-pw']))->assertSessionHasErrors('password');
        $this->post('/register', $this->form(['birth_year' => $tooYoung]))->assertSessionHasErrors('birth_year');
        $this->post('/register', $this->form(['agree' => null]))->assertSessionHasErrors('agree');
        $this->post('/register', $this->form(['gender' => 'x']))->assertSessionHasErrors('gender');
        $this->assertSame(0, User::count());
    }

    public function test_register_is_rate_limited_per_ip(): void
    {
        config(['loveuno.registration_open' => true]);
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $this->form(['email' => "u{$i}@example.com"]));
        }
        $this->post('/register', $this->form(['email' => 'sixth@example.com']))->assertStatus(429);
        $this->assertNull(User::where('email', 'sixth@example.com')->first());
    }

    public function test_rules_page_is_public(): void
    {
        $this->get('/rules')->assertOk()->assertSee('會員規範');
    }
}
