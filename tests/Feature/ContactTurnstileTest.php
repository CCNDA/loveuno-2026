<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 熊叔叔 11/6 決定 #13：聯絡我們照設計加「預覽」＋機器人驗證（Cloudflare Turnstile）
class ContactTurnstileTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        return array_merge(['name' => '測試者', 'email' => 'a@example.com', 'subject' => array_key_first(ContactMessage::SUBJECTS),
            'body' => '這是十個字以上的聯絡內容，請幫忙看看。'], $over);
    }

    public function test_page_shows_preview_and_widget_only_when_configured(): void
    {
        config(['loveuno.turnstile.site' => '', 'loveuno.turnstile.secret' => '']);
        $this->get('/contact')->assertOk()->assertSee('id="contact-preview"', false)->assertDontSee('cf-turnstile', false);
        config(['loveuno.turnstile.site' => 'site-key-x', 'loveuno.turnstile.secret' => 'secret-x']);
        $this->get('/contact')->assertOk()->assertSee('data-sitekey="site-key-x"', false)->assertDontSee('secret-x');
    }

    public function test_failed_or_missing_token_is_rejected_and_good_token_saves(): void
    {
        config(['loveuno.turnstile.site' => 'site-key-x', 'loveuno.turnstile.secret' => 'secret-x']);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);

        $this->post('/contact', $this->form())->assertSessionHasErrors('turnstile'); // 沒有 token
        $this->post('/contact', $this->form(['cf-turnstile-response' => 'bad']))->assertSessionHasErrors('turnstile');
        $this->assertSame(0, ContactMessage::count());
        $this->post('/contact', $this->form(['cf-turnstile-response' => 'good']))->assertSessionHasNoErrors()->assertRedirect('/contact');
        $this->assertSame(1, ContactMessage::count());
        Http::assertSent(fn ($r) => $r['secret'] === 'secret-x' && $r['response'] === 'good');
    }

    public function test_not_configured_skips_check(): void
    {
        config(['loveuno.turnstile.site' => '', 'loveuno.turnstile.secret' => '']);
        Http::fake();
        $this->post('/contact', $this->form())->assertSessionHasNoErrors();
        $this->assertSame(1, ContactMessage::count());
        Http::assertNothingSent();
    }
}
