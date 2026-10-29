<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\Meetup;
use App\Models\Profile;
use App\Models\User;
use App\Support\Points;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MeetupTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, string $gender = '女', array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => $gender, 'birth_year' => 1990]);
        Points::daily($u, 'daily_login'); // 先給掉今天的登入點，下面算點數才不會多 1

        return $u;
    }

    private function form(array $over = []): array
    {
        return array_merge(['meet_on' => today()->addDays(7)->toDateString(), 'meet_time' => '10:30', 'place' => '台北靈糧堂',
            'activity' => 'sunday', 'company' => 'friends', 'message' => '一起做主日吧'], $over);
    }

    private function pair(int $level = 2): array
    {
        $he = $this->member('弟兄', '男');
        $she = $this->member('姊妹');
        Points::award($he, 'test', 'seed', 29, '測試'); // 加上每日登入 1 點＝30
        if ($level > 0) {
            Friendship::raise($he, $she, $level);
        }

        return [$he->fresh(), $she];
    }

    public function test_must_be_chat_friends_and_costs_ten_points(): void
    {
        Notification::fake();
        [$he, $she] = $this->pair(1);
        $this->actingAs($he)->get("/members/{$she->id}/date")->assertOk()->assertSee('要先跟 姊妹 成為「聊天好友」');
        $this->actingAs($he)->post("/members/{$she->id}/date", $this->form())->assertSessionHasErrors('meetup');
        $this->assertSame(0, Meetup::count());

        Friendship::raise($he, $she, 2);
        $this->actingAs($he)->get("/members/{$she->id}/date")->assertOk()->assertSee('邀請你(妳)於');
        $this->actingAs($he)->post("/members/{$she->id}/date", $this->form(['meet_on' => today()->toDateString(), 'meet_time' => '10:15', 'activity' => 'x']))
            ->assertSessionHasErrors(['meet_on', 'meet_time', 'activity']);
        $this->actingAs($he)->post("/members/{$she->id}/date", $this->form())->assertRedirect();
        $m = Meetup::sole();
        $this->assertSame('pending', $m->status);
        $this->assertSame(20, $he->fresh()->points);
        $this->actingAs($she)->get('/')->assertSee('您有 1 個約會邀請等您回覆');
        $this->actingAs($she)->getJson('/push/summary')->assertJsonFragment(['body' => '您有 1 個約會邀請等您回覆。']);
        // 還沒結束的邀請不能再送一張（不會扣兩次）
        $this->actingAs($he)->post("/members/{$she->id}/date", $this->form())->assertSessionHasErrors('meetup');
        $this->assertSame(1, Meetup::count());
        $this->assertSame(20, $he->fresh()->points);
    }

    public function test_not_enough_points_sends_nothing(): void
    {
        [$he, $she] = $this->pair();
        $poor = $this->member('沒點數', '男');
        Friendship::raise($poor, $she, 2);
        $this->actingAs($poor)->post("/members/{$she->id}/date", $this->form())
            ->assertSessionHasErrors(['meetup' => '愛諾積點不夠（邀請約會要 10 點）。可以到「會員中心 → 愛積點」看怎麼得到積點。']);
        $this->assertSame(0, Meetup::count());
        $this->assertSame(1, $poor->fresh()->points); // 只有每日登入的 1 點，沒有被扣
    }

    public function test_accept_attend_becomes_real_friends(): void
    {
        Notification::fake();
        [$he, $she] = $this->pair();
        $this->actingAs($he)->post("/members/{$she->id}/date", $this->form());
        $m = Meetup::sole();
        $stranger = $this->member('路人');
        $this->actingAs($stranger)->get("/dates/{$m->id}")->assertNotFound();
        $this->actingAs($he)->post("/dates/{$m->id}/respond", ['decision' => 'accept', 'reply' => '好'])->assertForbidden(); // 只有被邀請的人

        $this->actingAs($she)->get("/dates/{$m->id}")->assertOk()->assertSee('弟兄 邀請您約會')->assertSee('台北靈糧堂');
        $this->actingAs($she)->post("/dates/{$m->id}/respond", ['decision' => 'accept'])->assertSessionHasErrors('reply');
        $this->actingAs($she)->post("/dates/{$m->id}/respond", ['decision' => 'accept', 'reply' => '好啊！'])->assertRedirect();
        $this->assertSame('accepted', $m->fresh()->status);
        // 約會日還沒到：不能確認出席
        $this->actingAs($she)->post("/dates/{$m->id}/attend", ['attended' => 'yes', 'closing_note' => '很開心'])->assertSessionHas('status', fn ($s) => str_contains($s, '狀態已經變了'));
        $this->assertSame('accepted', $m->fresh()->status);

        $this->travel(8)->days();
        $this->actingAs($he)->get("/dates/{$m->id}")->assertSee('等 姊妹 確認有沒有參加');
        $this->actingAs($he)->post("/dates/{$m->id}/attend", ['attended' => 'yes', 'closing_note' => 'x'])->assertForbidden(); // 邀請的人不能確認（舊站也是）
        $this->actingAs($she)->post("/dates/{$m->id}/attend", ['attended' => 'yes', 'closing_note' => '很開心'])->assertRedirect()
            ->assertSessionHas('status', '您和 弟兄 成為「真實好友」了！');
        $this->assertSame('attended', $m->fresh()->status);
        $this->assertSame(3, Friendship::between($he, $she)->level);
    }

    public function test_decline_cancel_and_expired(): void
    {
        Notification::fake();
        [$he, $she] = $this->pair();
        $this->actingAs($he)->post("/members/{$she->id}/date", $this->form());
        [$a] = Meetup::orderBy('id')->get()->all();

        $this->actingAs($she)->post("/dates/{$a->id}/respond", ['decision' => 'decline', 'reply' => '那天有事'])->assertRedirect();
        $this->assertSame('declined', $a->fresh()->status);
        $this->actingAs($she)->post("/dates/{$a->id}/respond", ['decision' => 'accept', 'reply' => '改變主意'])->assertSessionHas('status', fn ($s) => str_contains($s, '狀態已經變了')); // 只能回一次
        $this->assertSame('declined', $a->fresh()->status);
        // 婉拒了就可以再邀請
        $this->actingAs($he)->post("/members/{$she->id}/date", $this->form(['meet_on' => today()->addDays(3)->toDateString()]))->assertRedirect();
        $b = Meetup::latest('id')->first();

        $this->actingAs($she)->post("/dates/{$b->id}/respond", ['decision' => 'accept', 'reply' => '好'])->assertRedirect();
        $this->actingAs($he)->post("/dates/{$b->id}/cancel", [])->assertSessionHasErrors('closing_note');
        $this->actingAs($he)->post("/dates/{$b->id}/cancel", ['closing_note' => '臨時出差'])->assertRedirect(); // 雙方都可以取消
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->actingAs($he)->post("/members/{$she->id}/date", $this->form(['meet_on' => today()->addDays(2)->toDateString()]))->assertRedirect();
        $c = Meetup::latest('id')->first();
        $this->assertSame(0, $he->fresh()->points); // 3 次 × 10 點，不退

        // 沒回覆、日期過了
        $this->travel(2)->days();
        $this->assertSame('expired', $c->fresh()->state());
        $this->actingAs($she)->post("/dates/{$c->id}/respond", ['decision' => 'accept', 'reply' => '好'])->assertSessionHas('status', fn ($s) => str_contains($s, '狀態已經變了'));
        $this->assertSame('pending', $c->fresh()->status);
        $this->actingAs($she)->get('/dates')->assertOk()->assertSee('超過約會時間，沒有回覆')->assertSee('婉拒了')->assertSee('取消了');
    }

    public function test_block_hides_and_admin_list_and_delete_account(): void
    {
        Notification::fake();
        [$he, $she] = $this->pair();
        $this->actingAs($he)->post("/members/{$she->id}/date", $this->form());
        $m = Meetup::sole();
        $admin = $this->member('管理員', '女', ['is_admin' => true]);
        $this->actingAs($she)->get('/admin/dates')->assertForbidden();
        $this->actingAs($admin)->get('/admin/dates')->assertOk()->assertSee('台北靈糧堂');

        \Illuminate\Support\Facades\DB::table('blocks')->insert(['blocker_id' => $she->id, 'blocked_id' => $he->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($he)->get("/dates/{$m->id}")->assertNotFound();
        $this->actingAs($she)->get('/dates')->assertOk()->assertDontSee(route('meetups.show', $m), false);
    }
}
