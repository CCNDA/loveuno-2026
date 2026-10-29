<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        return $u;
    }

    public function test_send_and_read_a_note(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');

        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => "你好\n第二行"])->assertRedirect("/notes/{$b->id}");
        $n = Note::firstOrFail();
        $this->assertSame([$a->id, $b->id, null], [$n->sender_id, $n->recipient_id, $n->read_at]);

        // 乙的清單有一則未讀
        $this->actingAs($b)->get('/notes')->assertOk()->assertSee('甲')->assertSee('，1 則未讀');
        // 乙打開對話 → 讀過、內容換行保留
        $this->actingAs($b)->get("/notes/{$a->id}")->assertOk()->assertSee('你好<br />', false);
        $this->assertNotNull($n->fresh()->read_at);
        // 甲看得到「已讀」
        $this->actingAs($a)->get("/notes/{$b->id}")->assertSee('已讀');
    }

    public function test_third_party_cannot_see_others_notes(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $c = $this->member('丙');
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '甲給乙的祕密']);

        // 丙看自己跟甲的對話，看不到甲給乙的
        $this->actingAs($c)->get("/notes/{$a->id}")->assertOk()->assertDontSee('甲給乙的祕密');
        $this->actingAs($c)->get('/notes')->assertDontSee('甲給乙的祕密');
        // 丙打開跟甲的對話，不會把乙的紙條標成已讀
        $this->assertNull(Note::first()->read_at);
    }

    public function test_cannot_note_self_or_unverified_and_validation(): void
    {
        $a = $this->member('甲');
        $pending = User::factory()->unverified()->create();
        $this->actingAs($a)->get("/notes/{$a->id}")->assertNotFound();
        $this->actingAs($a)->post("/notes/{$a->id}", ['body' => 'x'])->assertNotFound();
        $this->actingAs($a)->post("/notes/{$pending->id}", ['body' => 'x'])->assertNotFound();

        $b = $this->member('乙');
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => ''])->assertSessionHasErrors('body');
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => str_repeat('字', 1001)])->assertSessionHasErrors('body');
        // sender／recipient 不能從表單偽造
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => 'ok', 'sender_id' => $b->id, 'recipient_id' => $a->id]);
        $this->assertSame([$a->id, $b->id], [Note::first()->sender_id, Note::first()->recipient_id]);
        $this->assertSame(1, Note::count());
    }

    public function test_body_is_escaped_and_guest_blocked(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '<script>alert(1)</script>']);
        $this->actingAs($b)->get("/notes/{$a->id}")->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);

        auth()->logout();
        $this->get('/notes')->assertRedirect('/login');
        $this->post("/notes/{$b->id}", ['body' => 'x'])->assertRedirect('/login');
    }

    public function test_cannot_open_or_send_to_missing_unverified_or_profileless(): void
    {
        $a = $this->member('甲');
        $pending = User::factory()->unverified()->create();
        $noProfile = User::factory()->create();
        $this->actingAs($a)->get('/notes/999999')->assertNotFound();
        $this->actingAs($a)->get("/notes/{$pending->id}")->assertNotFound();
        $this->actingAs($a)->get("/notes/{$noProfile->id}")->assertNotFound();
        $this->actingAs($a)->post("/notes/{$noProfile->id}", ['body' => 'x'])->assertNotFound();
        // 檔案頁不出現按鈕
        $this->actingAs($a)->get("/members/{$noProfile->id}")->assertDontSee('傳紙條給');
        $b = $this->member('乙');
        $this->actingAs($a)->get("/members/{$b->id}")->assertSee('傳紙條給 乙');
        $this->actingAs($a)->get("/members/{$a->id}")->assertDontSee('傳紙條給');
    }

    public function test_sender_opening_thread_does_not_mark_read_and_threads_sorted(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $c = $this->member('丙');
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '給乙']);
        $this->actingAs($a)->get("/notes/{$b->id}");
        $this->assertNull(\App\Models\Note::first()->read_at); // 自己打開自己寄的，不算對方讀過

        $this->actingAs($b)->post("/notes/{$a->id}", ['body' => '乙回甲']);
        $this->actingAs($c)->post("/notes/{$a->id}", ['body' => '丙給甲1']);
        $this->actingAs($c)->post("/notes/{$a->id}", ['body' => '丙給甲2']);
        $html = $this->actingAs($a)->get('/notes')->getContent();
        // 丙最新、排第一；丙 2 則未讀、乙 1 則
        $this->assertLessThan(strpos($html, '乙回甲'), strpos($html, '丙給甲2'));
        $this->assertStringContainsString('，2 則未讀', $html);
        $this->assertStringContainsString('，1 則未讀', $html);
    }

    public function test_rate_limits_are_independent(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        // 寄 30 張紙條（用完傳紙條的額度）之後，換密碼、存檔還是可以
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($a)->post("/notes/{$b->id}", ['body' => "第{$i}張"]);
        }
        $this->actingAs($a)->put('/me', ['name' => '甲'])->assertRedirect("/members/{$a->id}");
        $this->actingAs($a)->put('/center/preferences', [])->assertRedirect('/center/preferences');
    }

    public function test_sending_is_rate_limited(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($a)->post("/notes/{$b->id}", ['body' => "第{$i}張"]);
        }
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '第31張'])->assertStatus(429);
        $this->assertSame(30, Note::count());
    }

    public function test_unread_badge_in_nav(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => 'hi']);
        $html = $this->actingAs($b)->get('/diaries')->assertSee('傳紙條')->getContent();
        // 頁首與底部選單各一次，數字本身 aria-hidden，螢幕閱讀器只念「有 1 則未讀」
        $this->assertSame(2, substr_count($html, '，有 1 則未讀'));
        $this->assertSame(2, substr_count($html, 'aria-hidden="true">1</span>'));
    }
}
