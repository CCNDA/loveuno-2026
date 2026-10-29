<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_registration_is_closed_by_default(): void
    {
        config(['loveuno.registration_open' => false]);
        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->form())->assertNotFound();
        $this->assertSame(0, User::count());
    }

    public function test_register_sends_verification_and_hashes_password(): void
    {
        config(['loveuno.registration_open' => true]);
        Notification::fake();

        $this->get('/register')->assertOk()->assertSee('加入會員');
        $this->post('/register', $this->form())->assertRedirect('/email/verify');

        $user = User::where('email', 'new+church@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->is_demo);
        $this->assertTrue(Hash::check('long-enough-pw', $user->password));
        $this->assertSame('女', $user->profile->gender);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_member_cannot_see_member_pages(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/diaries')->assertRedirect('/email/verify');
        $this->actingAs($user)->get('/members')->assertRedirect('/email/verify');
    }

    public function test_verification_link_verifies(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);

        $this->actingAs($user)->get($url)->assertRedirect('/diaries');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('someone-else@example.com')]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_validation_rules(): void
    {
        config(['loveuno.registration_open' => true]);
        // 這裡要送 6 次錯的表單，會撞到一小時 5 次的限流（限流另有一項測試）
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        User::factory()->create(['email' => 'taken@example.com']);
        $tooYoung = (int) now()->format('Y') - 17;

        $this->post('/register', $this->form(['email' => 'taken@example.com']))->assertSessionHasErrors('email');
        $this->post('/register', $this->form(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', $this->form(['password_confirmation' => 'different-pw']))->assertSessionHasErrors('password');
        $this->post('/register', $this->form(['birth_year' => $tooYoung]))->assertSessionHasErrors('birth_year');
        $this->post('/register', $this->form(['agree' => null]))->assertSessionHasErrors('agree');
        $this->post('/register', $this->form(['gender' => 'x']))->assertSessionHasErrors('gender');
        $this->assertSame(1, User::count());
    }

    public function test_register_is_rate_limited_per_ip(): void
    {
        config(['loveuno.registration_open' => true]);
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $this->form(['email' => "u{$i}@example.com"]));
            auth()->logout();
        }
        $this->post('/register', $this->form(['email' => 'sixth@example.com']))->assertStatus(429);
        $this->assertNull(User::where('email', 'sixth@example.com')->first());
    }
}
