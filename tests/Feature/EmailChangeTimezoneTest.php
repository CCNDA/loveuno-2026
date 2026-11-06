<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Notifications\EmailChanged;
use App\Notifications\VerifyNewEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailChangeTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $over = []): User
    {
        $u = User::factory()->create($over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_change_email_with_confirmation(): void
    {
        Notification::fake();
        $u = $this->member(['email' => 'old@example.com']);
        $this->actingAs($u)->post('/center/email', ['new_email' => 'new@example.com', 'current_password' => 'wrong'])->assertSessionHasErrorsIn('email', 'current_password');
        $this->actingAs($u)->post('/center/email', ['new_email' => 'new@example.com', 'current_password' => 'password'])->assertRedirect();
        $this->assertSame('old@example.com', $u->fresh()->email); // 還沒點確認信

        $url = null;
        Notification::assertSentTo(new AnonymousNotifiable, VerifyNewEmail::class, function ($n, $c, $to) use (&$url) {
            $url = $n->toMail($to)->actionUrl;

            return $to->routes['mail'] === 'new@example.com';
        });
        $other = $this->member();
        $this->actingAs($other)->get($url)->assertNotFound(); // 別人點不行
        $this->actingAs($u)->get($url)->assertRedirect();
        $this->assertSame('new@example.com', $u->fresh()->email);
        Notification::assertSentTo(new AnonymousNotifiable, EmailChanged::class, fn ($n, $c, $to) => $to->routes['mail'] === 'old@example.com');
        $this->actingAs($u)->get($url)->assertNotFound(); // 用過就失效
    }

    public function test_taken_email_looks_the_same_but_sends_nothing(): void
    {
        Notification::fake();
        $this->member(['email' => 'taken@example.com']);
        $u = $this->member();
        $this->actingAs($u)->post('/center/email', ['new_email' => 'Taken@Example.com', 'current_password' => 'password'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, '確認信已經寄到'));
        Notification::assertNothingSent();
        $this->assertNull($u->fresh()->email_pending);
    }

    public function test_times_shown_in_browser_timezone(): void
    {
        $a = $this->member();
        $b = $this->member();
        \Illuminate\Support\Carbon::setTestNow('2026-10-29 21:30:00'); // 台北
        $this->befriend($a, $b);
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '你好']);

        $this->actingAs($b)->withUnencryptedCookie('tz', 'Asia/Tokyo')->get("/notes/{$a->id}")->assertSee('22:30');
        $this->actingAs($b)->withUnencryptedCookie('tz', 'Not/AZone')->get("/notes/{$a->id}")->assertSee('21:30'); // 不認得的時區：台北
        $this->actingAs($b)->get('/center/network')->assertSee('Asia/Taipei');
    }
}
