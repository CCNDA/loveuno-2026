<?php

namespace Tests\Feature;

use App\Models\Gift;
use App\Models\Meetup;
use App\Models\Profile;
use App\Models\User;
use App\Support\Notifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 熊叔叔 11/6 決定 #9：查看禮物、約會邀請改「左清單＋右卡片」，清單可勾選刪除（只刪自己這邊）
class GiftMeetupListTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        return $u;
    }

    private function gift(User $from, User $to, int $item, string $msg, string $day): Gift
    {
        $g = new Gift;
        $g->forceFill(['sender_id' => $from->id, 'recipient_id' => $to->id, 'item' => $item, 'message' => $msg, 'sent_on' => $day])->save();

        return $g;
    }

    private function meetup(User $from, User $to, string $place): Meetup
    {
        $m = new Meetup;
        $m->forceFill(['sender_id' => $from->id, 'recipient_id' => $to->id, 'meet_on' => today()->addDays(10), 'meet_time' => '10:00',
            'place' => $place, 'activity' => 'sunday', 'company' => 'friends', 'message' => '', 'status' => 'pending'])->save();

        return $m;
    }

    public function test_gift_list_and_pane_and_delete_my_side(): void
    {
        [$a, $b, $c] = [$this->member('甲'), $this->member('乙'), $this->member('丙')];
        $g1 = $this->gift($b, $a, 1, '乙給甲', '2026-11-01');
        $g2 = $this->gift($c, $a, 2, '丙給甲', '2026-11-02');
        $g3 = $this->gift($a, $b, 3, '甲回乙', '2026-11-03');
        $other = $this->gift($b, $c, 4, '乙給丙', '2026-11-04');

        $html = $this->actingAs($a)->get('/gifts')->assertOk()->getContent();
        $this->assertStringContainsString('您有 <span class="threads-count">2</span> 個新禮物', $html);
        $this->assertSame(3, substr_count($html, 'name="ids[]"'));
        $this->assertStringContainsString('送你一份「雲彩圍繞」', $html);
        $this->assertStringContainsString('送出一份「恩雨滋潤」', $html);
        $this->assertStringNotContainsString('乙給丙', $html);

        // 只看跟乙的：卡片只有 g1、g3（左邊清單還是 3 列）
        $pane = $this->actingAs($a)->get('/gifts?with=' . $b->id)->assertOk()->getContent();
        $this->assertStringContainsString('id="gift-' . $g1->id . '"', $pane);
        $this->assertStringContainsString('id="gift-' . $g3->id . '"', $pane);
        $this->assertStringNotContainsString('id="gift-' . $g2->id . '"', $pane);
        $this->assertStringContainsString('has-thread', $pane);
        $this->assertSame(3, substr_count($pane, 'name="ids[]"'));

        // 刪 g1（收到的）、g3（送出的）、other（不是我的）→ 只刪 2 份，乙那邊還看得到
        $this->actingAs($a)->post(route('gifts.destroy'), ['ids' => [$g1->id, $g3->id, $other->id]])->assertRedirect('/gifts')
            ->assertSessionHas('status', '刪掉了 2 份禮物。');
        $this->assertNotNull($g1->fresh()->recipient_deleted_at);
        $this->assertNotNull($g3->fresh()->sender_deleted_at);
        $this->assertNull($other->fresh()->sender_deleted_at);
        $this->assertNull($other->fresh()->recipient_deleted_at);
        $this->actingAs($a)->get('/gifts')->assertDontSee('乙給甲')->assertDontSee('甲回乙')->assertSee('丙給甲');
        $this->actingAs($b)->get('/gifts')->assertSee('乙給甲')->assertSee('甲回乙');

        // 刪掉的不算新禮物
        $g4 = $this->gift($b, $a, 5, '再給甲', '2026-11-05');
        $this->assertSame(1, Gift::unopenedCountFor($a));
        $this->actingAs($a)->post(route('gifts.destroy'), ['ids' => [$g4->id]]);
        $this->assertSame(0, Gift::unopenedCountFor($a));
        $this->actingAs($a)->post(route('gifts.destroy'), [])->assertSessionHas('status', '請先勾選要刪除的禮物。');
    }

    public function test_meetup_list_and_delete_my_side(): void
    {
        [$a, $b, $c] = [$this->member('甲'), $this->member('乙'), $this->member('丙')];
        $m1 = $this->meetup($b, $a, '乙約甲的教會');
        $m2 = $this->meetup($a, $c, '甲約丙的教會');
        $other = $this->meetup($b, $c, '乙約丙的教會');

        $html = $this->actingAs($a)->get('/dates')->assertOk()->getContent();
        $this->assertStringContainsString('您有 <span class="threads-count">1</span> 個新約會邀請', $html);
        $this->assertSame(2, substr_count($html, 'name="ids[]"'));
        $this->assertStringContainsString('提出一個約會邀請', $html);
        $this->assertStringContainsString('我提出的約會邀請', $html);
        $this->assertStringNotContainsString('乙約丙的教會', $html);

        $pane = $this->actingAs($a)->get('/dates?with=' . $c->id)->assertOk()->getContent();
        $this->assertStringContainsString('id="date-' . $m2->id . '"', $pane);
        $this->assertStringNotContainsString('id="date-' . $m1->id . '"', $pane);

        $this->assertSame(1, Notifications::badgeCount($a));
        $this->actingAs($a)->post(route('meetups.destroy'), ['ids' => [$m1->id, $other->id]])->assertRedirect('/dates')
            ->assertSessionHas('status', '刪掉了 1 個約會邀請。');
        $this->assertNotNull($m1->fresh()->recipient_deleted_at);
        $this->assertNull($other->fresh()->recipient_deleted_at);
        $this->assertSame(0, Notifications::badgeCount($a)); // 刪掉的不算等我回覆
        $this->actingAs($a)->get('/dates')->assertDontSee('乙約甲的教會')->assertSee('甲約丙的教會');
        $this->actingAs($b)->get('/dates')->assertSee('乙約甲的教會'); // 乙那邊還在
    }
}
