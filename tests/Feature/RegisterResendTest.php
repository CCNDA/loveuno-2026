<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// 註冊完「請到信箱收信」那頁可以直接重寄（小霏 11/2 複測：原本要先登入才找得到）
class RegisterResendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['loveuno.registration_open' => true]);
    }

    public function test_resend_from_sent_page_uses_session_email(): void
    {
        Notification::fake();
        $this->post('/register', $this->registerForm(['email' => 'new.member@example.com']))->assertRedirect(route('register.sent'));
        $u = User::where('email', 'new.member@example.com')->firstOrFail();
        Notification::assertSentToTimes($u, VerifyEmail::class, 1);

        $this->get(route('register.sent'))->assertOk()->assertSee('還是沒收到？再寄一次');
        $this->post(route('register.resend'))->assertRedirect(route('register.sent'))->assertSessionHas('status', fn ($m) => str_contains($m, '重新寄出'));
        Notification::assertSentToTimes($u, VerifyEmail::class, 2);

        // 驗證過就不再寄，但畫面一樣
        $u->forceFill(['email_verified_at' => now()])->save();
        $this->post(route('register.resend'))->assertSessionHas('status', fn ($m) => str_contains($m, '重新寄出'));
        Notification::assertSentToTimes($u, VerifyEmail::class, 2);
    }

    public function test_without_session_no_button_and_cannot_choose_email(): void
    {
        Notification::fake();
        $u = User::factory()->unverified()->create(['email' => 'someone@example.com']);
        $this->get(route('register.sent'))->assertOk()->assertDontSee('再寄一次');
        // 沒有剛註冊的 session：不寄，叫他去登入；就算自己塞 email 也沒用
        $this->post(route('register.resend'), ['email' => 'someone@example.com'])->assertRedirect(route('register.sent'))
            ->assertSessionHas('status', fn ($m) => str_contains($m, '登入'));
        Notification::assertNothingSent();
    }

    private function registerForm(array $over = []): array
    {
        return $over + [
            'name' => '新會員', 'email' => 'x@example.com', 'password' => 'Very-Strong-Pass-2026', 'password_confirmation' => 'Very-Strong-Pass-2026',
            'gender' => '女', 'birth_year' => 1990, 'agree' => '1',
        ];
    }
}
