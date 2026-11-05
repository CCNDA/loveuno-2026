<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員'): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_relationship_shows_on_profile_and_list(): void
    {
        $me = $this->member('我');
        $you = $this->member('你');
        $this->actingAs($me)->get("/members/{$you->id}")->assertSee('與您關係：<strong>路人甲</strong>', false)->assertDontSee('解除關係');
        $this->actingAs($me)->get("/members/{$me->id}")->assertDontSee('與您關係')->assertSee(route('friends.index'), false);

        Friendship::raise($you, $me, 1);
        $this->actingAs($me)->get("/members/{$you->id}")->assertSee('與您關係：<strong>普通好友</strong>', false)->assertSee(route('friends.confirm', $you), false);
        $me->profile->forceFill(['pref_gender' => '女'])->save(); // 沒設交友條件不列人（11/4）
        $this->actingAs($me)->get('/members')->assertSee('<span class="member-level">普通好友</span>', false); // 11/5 照 Figma 改成珊瑚色字
    }

    public function test_one_row_per_pair_and_only_goes_up(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        Friendship::raise($a, $b, 2);
        Friendship::raise($b, $a, 1); // 反過來、比較低：不變
        $this->assertSame(1, Friendship::count());
        $this->assertSame(2, Friendship::between($b, $a)->level);
        Friendship::raise($b, $a, 3);
        $this->assertSame(3, Friendship::between($a, $b)->level);
    }

    public function test_notes_both_ways_raise_to_chat_friend(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        Friendship::raise($a, $b, 1);
        for ($i = 0; $i < Friendship::CHAT_NOTES; $i++) {
            $this->actingAs($a)->post("/notes/{$b->id}", ['body' => "甲的第{$i}則"]);
        }
        $this->assertSame(1, Friendship::between($a, $b)->level); // 只有一邊傳：不升
        for ($i = 0; $i < Friendship::CHAT_NOTES; $i++) {
            $this->actingAs($b)->post("/notes/{$a->id}", ['body' => "乙的第{$i}則"]);
        }
        $this->assertSame(2, Friendship::between($a, $b)->level);
    }

    public function test_notes_do_not_make_strangers_friends(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        for ($i = 0; $i < Friendship::CHAT_NOTES; $i++) {
            $this->actingAs($a)->post("/notes/{$b->id}", ['body' => "a{$i}"]);
            $this->actingAs($b)->post("/notes/{$a->id}", ['body' => "b{$i}"]);
        }
        $this->assertNull(Friendship::between($a, $b));
    }

    public function test_unfriend_asks_first_then_removes(): void
    {
        $me = $this->member('我');
        $you = $this->member('你');
        $stranger = $this->member('路人');
        Friendship::raise($me, $you, 2);

        $this->actingAs($me)->get("/members/{$stranger->id}/unfriend")->assertNotFound();
        $this->actingAs($me)->get("/members/{$you->id}/unfriend")->assertOk()->assertSee('聊天好友')->assertSee('確定解除');
        $this->actingAs($me)->delete("/members/{$you->id}/friend")->assertRedirect('/friends');
        $this->assertNull(Friendship::between($me, $you));
        $this->actingAs($me)->delete("/members/{$you->id}/friend")->assertNotFound();
    }

    public function test_friends_page_groups_by_level_and_hides_blocked(): void
    {
        $me = $this->member('我');
        $real = $this->member('真實的朋友');
        $chat = $this->member('聊天的朋友');
        $plain = $this->member('普通的朋友');
        Friendship::raise($me, $real, 3);
        Friendship::raise($chat, $me, 2);
        Friendship::raise($me, $plain, 1);

        $this->actingAs($me)->get('/friends')->assertOk()
            ->assertSeeInOrder(['真實好友（1）', '真實的朋友', '聊天好友（1）', '聊天的朋友', '普通好友（1）', '普通的朋友']);

        // 封鎖：好友關係一起解除
        $this->actingAs($me)->post("/members/{$plain->id}/block");
        $this->assertNull(Friendship::between($me, $plain));
        $this->actingAs($me)->get('/friends')->assertSee('普通好友（0）')->assertDontSee(route('members.show', $plain), false); // 提示訊息裡有名字，所以看連結
    }
}
