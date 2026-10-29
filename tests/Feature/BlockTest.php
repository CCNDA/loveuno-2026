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
        // 封鎖的人還看得到以前的紙條（留證據），但不能再寄
        $this->actingAs($a)->get('/notes')->assertSee('乙給甲的紙條')->assertSee('已封鎖');
        $this->actingAs($a)->get("/notes/{$b->id}")->assertOk()->assertSee('乙給甲的紙條')->assertSee('只能看以前的紙條')->assertDontSee('寫給 乙');
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '甲還想回'])->assertNotFound();
        // 被封鎖的乙：紙條清單看不到甲、打不開
        $this->actingAs($b)->get('/notes')->assertDontSee('甲</span>', false);
        $this->actingAs($b)->get("/notes/{$a->id}")->assertNotFound();
        // 甲（封鎖的人）看乙的檔案：顯示已封鎖、可以解除
        $this->actingAs($a)->get("/members/{$b->id}")->assertOk()->assertSee('您已封鎖這位會員')->assertDontSee('傳紙條給');
        // 帳號管理有封鎖名單
        $this->actingAs($a)->get('/center/account')->assertSee('封鎖名單')->assertSee('乙');
    }

    public function test_confirm_page_does_not_leak_and_more_paths(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $admin = $this->member('管理員');
        $admin->forceFill(['is_admin' => true])->save();
        $a->diaries()->create(['title' => '甲的日記標題', 'body' => 'x', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $a->profile->forceFill(['photo_path' => 'a.jpg', 'photo_status' => 'approved'])->save();
        \Illuminate\Support\Facades\Storage::fake('avatars');
        \Illuminate\Support\Facades\Storage::disk('avatars')->put('a.jpg', 'x');

        $this->actingAs($a)->post("/members/{$b->id}/block");

        // 乙打甲的確認頁、送出封鎖：都是 404（跟檔案頁一樣）
        $this->actingAs($b)->get("/members/{$a->id}/block")->assertNotFound();
        $this->actingAs($b)->post("/members/{$a->id}/block")->assertNotFound();
        // 乙的日記牆看不到甲的日記；甲的照片 404
        $this->actingAs($b)->get('/diaries')->assertDontSee('甲的日記標題');
        $this->actingAs($b)->get("/photos/{$a->id}")->assertNotFound();
        // 甲看乙的檔案頁，沒有乙的日記標題（只有已封鎖提示）
        $b->diaries()->create(['title' => '乙的日記標題', 'body' => 'x', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $this->actingAs($a)->get("/members/{$b->id}")->assertDontSee('乙的日記標題');
        // 管理員不受影響
        $this->actingAs($admin)->get("/photos/{$a->id}")->assertOk();
    }

    public function test_unread_badge_excludes_notes_i_can_no_longer_open(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '甲先寄給乙']);
        $this->actingAs($b)->post("/notes/{$a->id}", ['body' => '乙寄給甲']);
        // 甲封鎖乙：乙打不開甲的對話，所以甲寄的那張不再算乙的未讀
        $this->actingAs($a)->post("/members/{$b->id}/block");
        $this->assertSame(0, \App\Models\Note::unreadCountFor($b->fresh()));
        // 甲（封鎖的人）還能打開對話把乙寄來的讀掉
        $this->assertSame(1, \App\Models\Note::unreadCountFor($a->fresh()));
        $this->actingAs($a)->get("/notes/{$b->id}")->assertOk();
        $this->assertSame(0, \App\Models\Note::unreadCountFor($a->fresh()));
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
