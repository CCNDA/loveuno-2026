<?php

namespace Tests\Feature;

use App\Models\Gift;
use App\Models\Profile;
use App\Models\User;
use App\Support\Notifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 熊叔叔 11/7 TG「收到禮物的處理應該要有一個回禮和刪除，刪除可以退對方點數」（按甲）：
// 刪除還沒回禮的禮物＝婉拒，退 3 點給送禮的人、通知他；已經回禮（是朋友）的只從自己這邊拿掉、不退
class GiftRefundTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, int $points = 0): User
    {
        $u = User::factory()->create(['name' => $name, 'points' => $points]);
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        return $u;
    }

    private function gift(User $from, User $to): Gift
    {
        $g = new Gift;
        $g->forceFill(['sender_id' => $from->id, 'recipient_id' => $to->id, 'item' => 1, 'message' => '你好', 'sent_on' => today()])->save();

        return $g;
    }

    public function test_deleting_an_unreturned_gift_refunds_the_sender_once(): void
    {
        $sender = $this->member('送禮的人', 10);
        $me = $this->member('收禮的人');
        $g = $this->gift($sender, $me);

        $this->actingAs($me)->post('/gifts/delete', ['ids' => [$g->id]])
            ->assertRedirect('/gifts')->assertSessionHas('status', fn ($s) => str_contains($s, '已退 3 點'));
        $this->assertSame(13, (int) $sender->fresh()->points);
        $this->assertNotNull($g->fresh()->refunded_at);
        $this->assertNotNull($g->fresh()->recipient_deleted_at);

        // 再送一次同樣的刪除也不會重複退
        $this->actingAs($me)->post('/gifts/delete', ['ids' => [$g->id]]);
        $this->assertSame(13, (int) $sender->fresh()->points);

        // 送禮的人通知鈴看得到
        $texts = array_column(Notifications::for($sender->fresh()), 'text');
        $this->assertContains('收禮的人 沒有收下你的禮物，3 點已退回。', $texts);
    }

    public function test_deleting_a_gift_from_a_friend_does_not_refund(): void
    {
        $sender = $this->member('朋友', 10);
        $me = $this->member('我');
        $this->befriend($sender, $me);
        $g = $this->gift($sender, $me);

        $this->actingAs($me)->post('/gifts/delete', ['ids' => [$g->id]])
            ->assertSessionHas('status', fn ($s) => ! str_contains($s, '已退'));
        $this->assertSame(10, (int) $sender->fresh()->points);
        $this->assertNull($g->fresh()->refunded_at);
    }

    public function test_sender_deleting_own_sent_gift_does_not_refund(): void
    {
        $sender = $this->member('送禮的人', 10);
        $other = $this->member('對方');
        $g = $this->gift($sender, $other);

        $this->actingAs($sender)->post('/gifts/delete', ['ids' => [$g->id]]);
        // 積點可能因每日登入 +1，所以看有沒有退點紀錄，不看餘額
        $this->assertDatabaseMissing('point_transactions', ['type' => 'gift_refund', 'ref' => 'gift:' . $g->id]);
        $this->assertNull($g->fresh()->refunded_at);
    }

    public function test_deleting_a_gift_i_already_returned_does_not_refund(): void
    {
        $sender = $this->member('送禮的人', 10);
        $me = $this->member('我', 10);
        $g = $this->gift($sender, $me);
        $this->gift($me, $sender); // 我回過禮（還沒成為朋友也算）

        $this->actingAs($me)->post('/gifts/delete', ['ids' => [$g->id]]);
        $this->assertDatabaseMissing('point_transactions', ['type' => 'gift_refund', 'ref' => 'gift:' . $g->id]);
        $this->assertNull($g->fresh()->refunded_at);
    }

    public function test_cannot_refund_someone_elses_gift(): void
    {
        $sender = $this->member('送禮的人', 10);
        $recipient = $this->member('收禮的人');
        $stranger = $this->member('路人');
        $g = $this->gift($sender, $recipient);

        $this->actingAs($stranger)->post('/gifts/delete', ['ids' => [$g->id]]);
        $this->assertSame(10, (int) $sender->fresh()->points);
        $this->assertNull($g->fresh()->refunded_at);
    }
}
