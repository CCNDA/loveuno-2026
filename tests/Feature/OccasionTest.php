<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\Profile;
use App\Models\User;
use App\Support\Occasions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OccasionTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, array $profile = []): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990] + $profile);

        return $u;
    }

    public function test_birthday_saved_and_validated(): void
    {
        $u = $this->member('我');
        $form = ['name' => '我', 'live_place' => '', 'job' => ''];
        $this->actingAs($u)->get('/me/edit')->assertSee('生日（月、日，可以不填）')->assertSee('不會顯示日期');
        $this->actingAs($u)->put('/me', $form + ['birth_month' => 3, 'birth_day' => 15])->assertRedirect();
        $this->assertSame([3, 15], [(int) $u->profile->fresh()->birth_month, (int) $u->profile->fresh()->birth_day]);

        $this->actingAs($u)->put('/me', $form + ['birth_month' => 2, 'birth_day' => 30])->assertSessionHasErrors(['birth_day' => '2 月沒有 30 日。']);
        $this->actingAs($u)->put('/me', $form + ['birth_month' => 4])->assertSessionHasErrors('birth_day');
        $this->actingAs($u)->put('/me', $form + ['birth_month' => 2, 'birth_day' => 29])->assertRedirect(); // 閏年生日可以

        // 生日日期不會出現在檔案頁
        $other = $this->member('別人');
        $this->actingAs($other)->get("/members/{$u->id}")->assertDontSee('2 月')->assertDontSee('29 日');
    }

    public function test_friend_birthday_within_a_week_shows_on_wall(): void
    {
        Carbon::setTestNow('2026-10-29 10:00:00');
        $me = $this->member('我');
        $soon = $this->member('快生日的', ['birth_month' => 11, 'birth_day' => 3]);
        $today = $this->member('今天生日的', ['birth_month' => 10, 'birth_day' => 29]);
        $later = $this->member('還早的', ['birth_month' => 12, 'birth_day' => 25]);
        $stranger = $this->member('路人', ['birth_month' => 10, 'birth_day' => 30]);
        foreach ([$soon, $today, $later] as $f) {
            Friendship::raise($me, $f, 1);
        }

        $page = $this->actingAs($me)->get('/')->assertSee('特殊節日')->getContent();
        // 只看「特殊節日」那一塊（好友推薦、歡迎新朋友也會列出這些人）
        $start = strpos($page, 'id="occasion-title"');
        $html = substr($page, $start, strpos($page, '</section>', $start) - $start);
        $this->assertStringContainsString('今天生日的</a>
                            今天生日！', $html);
        $this->assertStringContainsString('快生日的</a>
                            生日快到了！', $html);
        $this->assertStringNotContainsString('還早的', $html);
        $this->assertStringNotContainsString('>路人<', $html); // 不是好友
        $this->assertStringContainsString(route('gifts.create', $soon), $html);
    }

    public function test_anniversary_and_year_wrap(): void
    {
        Carbon::setTestNow('2026-12-29 10:00:00');
        $me = $this->member('我');
        $old = $this->member('老朋友');
        $new = $this->member('新朋友');
        $f = Friendship::raise($me, $old, 2);
        DB::table('friendships')->where('id', $f->id)->update(['created_at' => '2024-01-02 09:00:00']);
        Friendship::raise($me, $new, 1); // 今天才成為好友：不算週年

        $list = Occasions::for($me);
        $this->assertCount(1, $list);
        $this->assertSame(['anniversary', 3, 4], [$list[0]['type'], $list[0]['years'], $list[0]['in_days']]); // 跨年：2024/1/2 → 2027/1/2 滿 3 年，還有 4 天
    }

    public function test_blocked_friend_not_listed(): void
    {
        Carbon::setTestNow('2026-10-29 10:00:00');
        $me = $this->member('我');
        $f = $this->member('朋友', ['birth_month' => 10, 'birth_day' => 30]);
        Friendship::raise($me, $f, 1);
        $this->assertCount(1, Occasions::for($me));
        DB::table('blocks')->insert(['blocker_id' => $f->id, 'blocked_id' => $me->id, 'created_at' => now(), 'updated_at' => now()]);
        request()->attributes->remove('block_ids:' . $me->id);
        $this->assertCount(0, Occasions::for($me));
    }
}
