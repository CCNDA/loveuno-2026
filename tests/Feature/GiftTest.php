<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\Gift;
use App\Models\Profile;
use App\Models\User;
use App\Support\Points;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GiftTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', int $points = 10): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);
        if ($points > 0) {
            Points::award($u, 'test', 'seed', $points, '測試用');
        }

        return $u;
    }

    private function balance(User $u): int
    {
        return (int) DB::table('users')->where('id', $u->id)->value('points');
    }

    public function test_gift_page_follows_design(): void
    {
        $me = $this->member('我');
        $you = $this->member('你');
        $this->actingAs($me)->get("/members/{$you->id}")->assertSee(route('gifts.create', $you), false);
        $this->actingAs($me)->get("/members/{$you->id}/gift")->assertOk()
            ->assertSee('送一份禮物給陌生網友是對他(她)表達好感的第一步')
            ->assertSeeInOrder(['雲彩圍繞', '堅固房角石', '恩雨滋潤', '恩膏葡萄酒', '守護者天使', '聖經的奧秘', '雲上太陽', '聖靈充滿', '五餅二魚', '忠心小驢駒', '主的小羊', '初熟羊肉爐'])
            ->assertSee('20 個中文字以內');
        $this->actingAs($me)->get("/members/{$me->id}/gift")->assertNotFound();
    }

    public function test_send_costs_three_and_once_a_day_per_person(): void
    {
        $me = $this->member('我');
        $you = $this->member('你');
        $this->actingAs($me)->post("/members/{$you->id}/gift", ['step' => 'send', 'item' => 11, 'message' => '願主祝福你'])
            ->assertRedirect("/members/{$you->id}")->assertSessionHas('status', '禮物送給 你 了。');
        $this->assertSame(10 + 1 - 3, $this->balance($me)); // +1 是每日登入
        $g = Gift::firstOrFail();
        $this->assertSame([11, '願主祝福你', $me->id, $you->id], [$g->item, $g->message, $g->sender_id, $g->recipient_id]);

        $this->actingAs($me)->post("/members/{$you->id}/gift", ['step' => 'send', 'item' => 1])->assertSessionHasErrors('item');
        $this->assertSame(1, Gift::count());
        $this->assertSame(8, $this->balance($me));

        // 隔天可以再送
        $this->travel(1)->days();
        $this->actingAs($me)->post("/members/{$you->id}/gift", ['step' => 'send', 'item' => 1])->assertRedirect();
        $this->assertSame(2, Gift::count());
    }

    public function test_not_enough_points_sends_nothing(): void
    {
        $me = $this->member('我', 0);
        $you = $this->member('你');
        $this->actingAs($me)->post("/members/{$you->id}/gift", ['step' => 'send', 'item' => 1])
            ->assertSessionHasErrors(['item' => '愛諾積點不夠（送禮物要 3 點）。可以到「會員中心 → 愛積點」看怎麼得到積點。']);
        $this->assertSame(0, Gift::count());
        $this->assertSame(1, $this->balance($me)); // 只有每日登入那 1 點

        $this->actingAs($me)->post("/members/{$you->id}/gift", ['step' => 'send', 'item' => 1])
            ->assertSessionHasErrors(['item' => '愛諾積點不夠（送禮物要 3 點）。可以到「會員中心 → 愛積點」看怎麼得到積點。']);
        $this->assertSame(0, Gift::count());
    }

    public function test_validation(): void
    {
        $me = $this->member('我');
        $you = $this->member('你');
        $this->actingAs($me)->post("/members/{$you->id}/gift", [])->assertSessionHasErrors(['item' => '請選擇禮物。']);
        $this->actingAs($me)->post("/members/{$you->id}/gift", ['item' => 13])->assertSessionHasErrors('item');
        $this->actingAs($me)->post("/members/{$you->id}/gift", ['item' => 1, 'message' => str_repeat('字', 21)])->assertSessionHasErrors(['message' => '簡訊要在 20 個字以內。']);
        $this->assertSame(0, Gift::count());
    }

    public function test_exchanging_gifts_makes_plain_friends(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['step' => 'send', 'item' => 1]);
        $this->assertNull(Friendship::between($a, $b));

        $this->actingAs($b)->get('/')->assertSee('您收到 1 份新禮物');
        $this->actingAs($b)->get('/gifts')->assertOk()->assertSee('甲')->assertSee('雲彩圍繞')->assertSee('回送禮物，成為好友');
        $this->actingAs($b)->get('/')->assertDontSee('份新禮物'); // 看過了

        $this->actingAs($b)->post("/members/{$a->id}/gift", ['step' => 'send', 'item' => 2])->assertSessionHas('status', '您和 甲 交換禮物成功，成為「普通好友」了！');
        $this->assertSame(1, Friendship::between($a, $b)->level);
        $this->actingAs($a)->get("/members/{$b->id}")->assertSee('與您關係：<strong>普通好友</strong>', false);
    }

    public function test_blocked_cannot_send_and_gifts_hidden(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['step' => 'send', 'item' => 1]);
        DB::table('blocks')->insert(['blocker_id' => $b->id, 'blocked_id' => $a->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($a)->get("/members/{$b->id}/gift")->assertNotFound();
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['step' => 'send', 'item' => 1])->assertNotFound();
        $this->actingAs($b)->get('/gifts')->assertDontSee(route('members.show', $a), false);
    }

    public function test_cannot_send_to_unverified_or_profileless_or_without_own_profile(): void
    {
        $me = $this->member('我');
        $unverified = $this->member('沒驗證');
        $unverified->forceFill(['email_verified_at' => null])->save();
        $noProfile = User::factory()->create(['name' => '沒檔案']);
        foreach ([$unverified, $noProfile] as $u) {
            $this->actingAs($me)->get("/members/{$u->id}/gift")->assertNotFound();
            $this->actingAs($me)->post("/members/{$u->id}/gift", ['step' => 'send', 'item' => 1])->assertNotFound();
        }
        $you = $this->member('你');
        $this->actingAs($noProfile)->get("/members/{$you->id}/gift")->assertNotFound(); // 自己沒有檔案
        $this->assertSame(0, Gift::count());
    }

    public function test_opening_gifts_page_marks_received_as_opened(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['step' => 'send', 'item' => 1]);
        $this->assertNull(Gift::first()->opened_at);
        $this->actingAs($a)->get('/gifts'); // 送的人看：不算
        $this->assertNull(Gift::first()->opened_at);
        $this->actingAs($b)->get('/gifts')->assertSee('<span class="tag">新</span>', false);
        $this->assertNotNull(Gift::first()->opened_at);
        $this->actingAs($a)->get('/gifts')->assertDontSee('對方看過了'); // 沒有讀取回條
    }

    public function test_after_unfriend_both_must_send_again(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['step' => 'send', 'item' => 1]);
        $this->actingAs($b)->post("/members/{$a->id}/gift", ['step' => 'send', 'item' => 1]);
        $this->assertNotNull(Friendship::between($a, $b));

        $this->actingAs($a)->delete("/members/{$b->id}/friend");
        $this->assertSame(0, Gift::count());
        $this->travel(1)->days();
        $this->actingAs($b)->post("/members/{$a->id}/gift", ['step' => 'send', 'item' => 2]); // 只有乙送：還不是好友
        $this->assertNull(Friendship::between($a, $b));
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['step' => 'send', 'item' => 3]);
        $this->assertNotNull(Friendship::between($a, $b));
    }

    public function test_welcome_new_friends_on_wall(): void
    {
        $me = $this->member('我');
        $new = $this->member('新來的');
        $old = $this->member('老會員');
        $old->forceFill(['email_verified_at' => now()->subDays(60)])->save();

        $html = $this->actingAs($me)->get('/')->assertSee('歡迎新朋友')->getContent();
        $this->assertStringContainsString(route('gifts.create', $new), $html);
        $this->assertStringNotContainsString(route('gifts.create', $old), $html);
        $this->assertStringNotContainsString(route('gifts.create', $me), $html);
    }

    // 會員反映誤觸就送出（熊叔叔 11/2 選 gift_both）：先看確認頁，按「確定送出」才送、才扣點
    public function test_first_submit_shows_confirm_page_without_sending(): void
    {
        $me = $this->member('我');
        $you = $this->member('你');
        $this->actingAs($me)->post("/members/{$you->id}/gift", ['item' => 3])->assertOk()
            ->assertSee('確認送禮物給 你')->assertSee('恩雨滋潤')
            ->assertSee('<strong>沒有寫</strong>', false)
            ->assertSee('會用 3 點（送出後剩 8 點）')
            ->assertSee('name="step" value="send"', false)->assertSee('name="step" value="edit"', false)
            ->assertSee('name="item" value="3"', false);
        $this->assertSame(0, Gift::count());
        $this->assertSame(11, $this->balance($me));

        $this->actingAs($me)->post("/members/{$you->id}/gift", ['item' => 5, 'message' => '<b>平安</b>'])->assertOk()
            ->assertSee('&lt;b&gt;平安&lt;/b&gt;', false)->assertDontSee('<b>平安</b>', false)
            ->assertDontSee('沒有寫');

        $this->actingAs($me)->post("/members/{$you->id}/gift", ['step' => 'send', 'item' => 5, 'message' => '平安'])
            ->assertRedirect("/members/{$you->id}");
        $this->assertSame(1, Gift::count());
        $this->assertSame(8, $this->balance($me));
    }

    public function test_edit_goes_back_to_form_with_choices_kept(): void
    {
        $me = $this->member('我');
        $you = $this->member('你');
        $this->actingAs($me)->from("/members/{$you->id}/gift")
            ->post("/members/{$you->id}/gift", ['step' => 'edit', 'item' => 7, 'message' => '想認識你'])
            ->assertRedirect("/members/{$you->id}/gift");
        $this->actingAs($me)->get("/members/{$you->id}/gift")->assertOk()
            ->assertSee('value="想認識你"', false)
            ->assertSee('id="item-7" name="item" type="radio" value="7" class="gift-radio"' . "\n" . '                           checked', false);
        $this->assertSame(0, Gift::count());
    }

    public function test_confirm_step_still_checks_sent_today(): void
    {
        $a = $this->member('甲');
        $b = $this->member('乙');
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['step' => 'send', 'item' => 1]);
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['item' => 2])
            ->assertSessionHasErrors(['item' => '今天已經送過禮物給 乙 了，明天再送吧。']);
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['step' => 'edit', 'item' => 2])->assertRedirect();
        $this->assertSame(1, Gift::count());
    }
}
