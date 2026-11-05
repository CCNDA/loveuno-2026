<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\ContactReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    private function form(array $over = []): array
    {
        return array_merge(['name' => '小明', 'email' => 'ming@example.com', 'subject' => 'service', 'body' => '我想請問怎麼修改大頭照，謝謝。', 'website' => ''], $over);
    }

    public function test_public_pages_open_without_login(): void
    {
        foreach (['/about' => '關於愛諾園', '/faq' => '常見問題', '/terms' => '網站服務條款', '/privacy' => '隱私權政策', '/contact' => '聯絡我們'] as $url => $title) {
            $this->get($url)->assertOk()->assertSee($title);
        }
        $this->get('/about')->assertSee('https://go.nien.co/3LUoTeH', false);
        $this->get('/')->assertSee(route('faq'), false)->assertSee(route('privacy'), false);
    }

    public function test_contact_saves_and_mails_support(): void
    {
        Notification::fake();
        $this->post('/contact', $this->form())->assertRedirect('/contact');
        $m = ContactMessage::sole();
        $this->assertSame(['小明', 'service', null], [$m->name, $m->subject, $m->user_id]);
        Notification::assertSentOnDemand(ContactReceived::class, function ($n, $channels, AnonymousNotifiable $to) use ($m) {
            $mail = $n->toMail($to);

            return $to->routes['mail'] === 'support@loveuno.com' && $mail->replyTo[0][0] === 'ming@example.com' && str_contains($mail->subject, '#' . $m->id);
        });

        $u = $this->member('我');
        $this->actingAs($u)->get('/contact?subject=bug')->assertSee('value="' . $u->email . '"', false)->assertSee('value="bug" selected', false);
        $this->actingAs($u)->post('/contact', $this->form(['subject' => 'bug']))->assertRedirect();
        $this->assertSame($u->id, ContactMessage::latest('id')->first()->user_id);
    }

    public function test_contact_validation_honeypot_and_throttle(): void
    {
        Notification::fake();
        $this->post('/contact', $this->form(['subject' => 'x', 'body' => '短']))->assertSessionHasErrors(['subject', 'body']);
        $this->post('/contact', $this->form(['website' => 'http://spam']))->assertRedirect('/contact');
        $this->assertSame(0, ContactMessage::count());
        Notification::assertNothingSent();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/contact', $this->form());
        }
        $this->post('/contact', $this->form())->assertStatus(429);
    }

    public function test_admin_contacts_list_and_handle(): void
    {
        $m = ContactMessage::create($this->form());
        $u = $this->member('我');
        $this->actingAs($u)->get('/admin/contacts')->assertForbidden();
        $admin = $this->member('管理員', ['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/contacts')->assertOk()->assertSee('我想請問怎麼修改大頭照');
        $this->actingAs($admin)->post('/admin/contacts/' . $m->id)->assertRedirect('/admin/contacts');
        $this->assertNotNull($m->fresh()->handled_at);
        $this->actingAs($admin)->get('/admin/contacts')->assertSee('目前沒有要處理的來信');
    }
}
