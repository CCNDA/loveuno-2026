<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\Note;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        return $u;
    }

    public function test_block_hides_both_ways_and_stops_notes(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $d = $b->diaries()->create(['title' => '乙的日記', 'body' => 'x', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $this->actingAs($b)->post("/notes/{$a->id}", ['body' => '乙給甲的紙條']);

        $this->actingAs($a)->get("/members/{$b->id}/block")->assertOk()->assertSee('確定要封鎖 乙 嗎');
        $this->actingAs($a)->post("/members/{$b->id}/block")->assertRedirect('/center/account');

        // 乙那邊：看不到甲、傳不了紙條
        $this->actingAs($b)->get("/members/{$a->id}")->assertNotFound();
        $this->actingAs($b)->post("/notes/{$a->id}", ['body' => '還想寄'])->assertNotFound();
        $this->actingAs($b)->get('/members')->assertDontSee('甲');
        $this->assertSame(1, Note::count());

        // 甲那邊：日記牆、列表、紙條清單都看不到乙；乙的日記 404
        $this->actingAs($a)->get('/diaries')->assertDontSee('乙的日記');
        $this->actingAs($a)->get("/diaries/{$d->id}")->assertNotFound();
        $this->actingAs($a)->get('/members')->assertDontSee('乙');
        $this->actingAs($a)->get('/notes')->assertDontSee('乙給甲的紙條');
        $this->actingAs($a)->get("/notes/{$b->id}")->assertNotFound();
        // 甲（封鎖的人）看乙的檔案：顯示已封鎖、可以解除
        $this->actingAs($a)->get("/members/{$b->id}")->assertOk()->assertSee('您已封鎖這位會員')->assertDontSee('傳紙條給');
        // 帳號管理有封鎖名單
        $this->actingAs($a)->get('/center/account')->assertSee('封鎖名單')->assertSee('乙');
    }

    public function test_unblock_restores(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $this->actingAs($a)->post("/members/{$b->id}/block");
        $this->actingAs($a)->delete("/members/{$b->id}/block")->assertRedirect();
        $this->actingAs($b)->get("/members/{$a->id}")->assertOk();
        $this->actingAs($b)->post("/notes/{$a->id}", ['body' => '和好'])->assertRedirect("/notes/{$a->id}");
    }

    public function test_cannot_block_self_and_double_block_is_harmless(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $this->actingAs($a)->post("/members/{$a->id}/block")->assertNotFound();
        $this->actingAs($a)->post("/members/{$b->id}/block");
        $this->actingAs($a)->post("/members/{$b->id}/block");
        $this->assertSame(1, \DB::table('blocks')->count());
        // 別人不能替甲解除
        $c = $this->member('丙');
        $this->actingAs($c)->delete("/members/{$b->id}/block");
        $this->assertSame(1, \DB::table('blocks')->count());
    }

    public function test_blocked_photo_is_hidden(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $b->profile->forceFill(['photo_path' => 'x.jpg', 'photo_status' => 'approved'])->save();
        $this->actingAs($b)->post("/members/{$a->id}/block");
        $this->assertFalse($b->profile->fresh()->photoVisibleTo($a));
    }
}
