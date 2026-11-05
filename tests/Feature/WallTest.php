<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WallTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, string $gender = '女', ?int $year = 1990, string $place = '台北市'): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => $gender, 'birth_year' => $year, 'live_place' => $place]);

        return $u;
    }

    public function test_guest_sees_intro_member_sees_wall(): void
    {
        $this->get('/')->assertOk()->assertSee('在信仰裡，認識另一半')->assertDontSee('好友推薦');
        $me = $this->member('我');
        $other = $this->member('別人');
        $other->diaries()->create(['title' => '牆上的日記', 'body' => '內容', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $this->actingAs($me)->get('/')->assertOk()->assertSee('好友推薦')->assertSee('牆上的日記')->assertSee('別人');
    }

    public function test_wall_hides_private_others_and_blocked(): void
    {
        $me = $this->member('我');
        $other = $this->member('別人');
        $bad = $this->member('壞人');
        $other->diaries()->create(['title' => '別人的私密', 'body' => 'x', 'visibility' => 'private', 'published_on' => now()->toDateString()]);
        $bad->diaries()->create(['title' => '壞人的日記', 'body' => 'x', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $this->actingAs($me)->post("/members/{$bad->id}/block");
        $this->actingAs($me)->get('/')->assertDontSee('別人的私密')->assertDontSee('壞人的日記')->assertDontSee("/members/{$bad->id}\"", false); // 封鎖後的提示會寫「已封鎖 壞人」，所以改看有沒有連到他的檔案
    }

    public function test_suggestions_follow_preferences(): void
    {
        $me = $this->member('我', '男', 1990, '台北市');
        $me->profile->forceFill(['pref_gender' => '女', 'pref_place' => '台中市', 'pref_age_min' => 30, 'pref_age_max' => 40])->save();
        $now = (int) now()->format('Y');
        $this->member('符合的姊妹', '女', $now - 35, '台中市');
        $this->member('太年輕', '女', $now - 25, '台中市');
        $this->member('別的城市', '女', $now - 35, '高雄市');
        $this->member('弟兄', '男', $now - 35, '台中市');

        $this->actingAs($me)->get('/')->assertSee('符合的姊妹')->assertDontSee('太年輕')->assertDontSee('別的城市')->assertDontSee('>弟兄<', false);
    }

    // 首頁推薦的年齡跟認識朋友一樣放寬到 5 歲一格：把條件設成 31～31 歲也試不出出生年（11/4 兩面審查）
    public function test_suggestions_age_widened_to_five_year_groups(): void
    {
        $me = $this->member('我', '男', 1990, '台北市');
        $me->profile->forceFill(['pref_age_min' => 31, 'pref_age_max' => 31])->save();
        $now = (int) now()->format('Y');
        $this->member('三十三歲', '女', $now - 33);
        $this->member('三十五歲', '女', $now - 35);
        $this->actingAs($me)->get('/')->assertSee('三十三歲')->assertDontSee('三十五歲');
    }

    public function test_suggestions_exclude_too_old_blockers_unverified_and_self(): void
    {
        $me = $this->member('我', '男', 1990, '台北市');
        $me->profile->forceFill(['pref_age_max' => 40])->save();
        $now = (int) now()->format('Y');
        $this->member('年紀太大', '女', $now - 50);
        $blocker = $this->member('封鎖我的人', '女', $now - 30);
        $this->actingAs($blocker)->post("/members/{$me->id}/block");
        $pending = User::factory()->unverified()->create(['name' => '還沒驗證']);
        Profile::create(['user_id' => $pending->id, 'gender' => '女', 'birth_year' => $now - 30]);
        $this->member('剛好', '女', $now - 40);

        $html = $this->actingAs($me)->get('/')->getContent();
        $this->assertStringContainsString('剛好', $html);
        foreach (['年紀太大', '封鎖我的人', '還沒驗證'] as $name) {
            $this->assertStringNotContainsString($name, $html, $name);
        }
        $this->assertStringNotContainsString('class="suggest-name">我<', $html);
    }

    public function test_unverified_member_gets_intro_not_wall(): void
    {
        $pending = User::factory()->unverified()->create();
        $this->actingAs($pending)->get('/')->assertOk()->assertDontSee('好友推薦');
    }

    // ── 動態牆：心情日記＋代禱事項、全部人／只看朋友、非朋友不能留言（熊叔叔 2026-11-05 TG）──

    private function writeDiary(User $u, string $title, bool $prayer): \App\Models\Diary
    {
        return $u->diaries()->create(['title' => $title, 'body' => '內容', 'visibility' => 'public', 'is_prayer' => $prayer, 'published_on' => now()->toDateString()]);
    }

    public function test_wall_shows_both_kinds_with_labels_and_kind_filter(): void
    {
        $me = $this->member('我');
        $other = $this->member('別人');
        $this->writeDiary($other, '今天的心情', false);
        $this->writeDiary($other, '請為我禱告', true);

        $this->actingAs($me)->get('/')->assertOk()->assertSee('動態牆')
            ->assertSee('今天的心情')->assertSee('請為我禱告')
            ->assertSee('<span class="tag tag-diary">心情日記</span>', false)
            ->assertSee('<span class="tag tag-prayer">代禱事項</span>', false);
        $this->actingAs($me)->get('/?kind=diary')->assertSee('今天的心情')->assertDontSee('請為我禱告');
        $this->actingAs($me)->get('/?kind=prayer')->assertSee('請為我禱告')->assertDontSee('今天的心情');
        $this->actingAs($me)->get('/?kind=<script>')->assertOk()->assertSee('今天的心情')->assertSee('請為我禱告'); // 亂填當全部
    }

    public function test_wall_friends_scope_shows_friends_and_me_only(): void
    {
        $me = $this->member('我');
        $friend = $this->member('朋友');
        $stranger = $this->member('路人');
        \App\Models\Friendship::raise($me, $friend, 1);
        $this->writeDiary($me, '我自己的', false);
        $this->writeDiary($friend, '朋友寫的', false);
        $this->writeDiary($stranger, '路人寫的', false);

        $this->actingAs($me)->get('/')->assertSee('路人寫的');
        $this->actingAs($me)->get('/?scope=friends')->assertSee('朋友寫的')->assertSee('我自己的')->assertDontSee('路人寫的');
        $this->actingAs($me)->get('/?scope=friends&kind=prayer')->assertSee('朋友們還沒有代禱事項');
    }

    public function test_pray_button_only_on_prayer_requests(): void
    {
        $me = $this->member('我');
        $other = $this->member('別人');
        $diary = $this->writeDiary($other, '今天的心情', false);
        $prayer = $this->writeDiary($other, '請為我禱告', true);

        $page = $this->actingAs($me)->get('/')->getContent();
        $this->assertStringContainsString(route('diaries.pray', $prayer), $page);
        $this->assertStringNotContainsString(route('diaries.pray', $diary), $page);
        $this->actingAs($me)->post(route('diaries.pray', $diary), ['from' => 'wall'])->assertNotFound();
        $this->actingAs($me)->post(route('diaries.pray', $prayer), ['from' => 'wall'])->assertRedirect();
        $this->assertSame(1, $prayer->prayers()->count());
    }

    public function test_only_friends_and_author_can_comment(): void
    {
        $author = $this->member('作者');
        $friend = $this->member('朋友');
        $stranger = $this->member('路人');
        \App\Models\Friendship::raise($author, $friend, 1);
        $d = $this->writeDiary($author, '今天的心情', false);

        // 路人：伺服器擋、畫面沒有留言框
        $this->actingAs($stranger)->post(route('diaries.comments.store', $d), ['body' => '路人留言', 'from' => 'show'])
            ->assertRedirect()->assertSessionHas('status', '只有作者的朋友可以留言。');
        $this->assertSame(0, $d->comments()->count());
        $this->actingAs($stranger)->get(route('diaries.show', $d))->assertSee('成為 作者 的朋友以後才能留言')
            ->assertDontSee('action="' . route('diaries.comments.store', $d) . '"', false);
        $this->actingAs($stranger)->get('/')->assertSee('成為 作者 的朋友以後才能留言');

        // 朋友、作者本人可以
        $this->actingAs($friend)->post(route('diaries.comments.store', $d), ['body' => '朋友留言', 'from' => 'show'])->assertRedirect();
        $this->actingAs($author)->post(route('diaries.comments.store', $d), ['body' => '作者回覆', 'from' => 'show'])->assertRedirect();
        $this->assertSame(['朋友留言', '作者回覆'], $d->comments()->orderBy('id')->pluck('body')->all());
        $this->actingAs($friend)->get('/')->assertSee('action="' . route('diaries.comments.store', $d) . '"', false);
    }

    public function test_wall_actions_keep_filters_and_ignore_bad_values(): void
    {
        $me = $this->member('我');
        $other = $this->member('別人');
        \App\Models\Friendship::raise($me, $other, 1);
        $p = $this->writeDiary($other, '請為我禱告', true);

        $this->actingAs($me)->post(route('diaries.pray', $p), ['from' => 'wall', 'wall_scope' => 'friends', 'wall_kind' => 'prayer'])
            ->assertRedirect(rtrim(route('home'), '/') . '/?scope=friends&kind=prayer#d' . $p->id);
        $this->actingAs($me)->post(route('diaries.comments.store', $p), ['body' => '加油', 'from' => 'wall', 'wall_scope' => 'x', 'wall_kind' => 'https://evil.example'])
            ->assertRedirect(rtrim(route('home'), '/') . '/#d' . $p->id);
        $this->actingAs($me)->get('/?scope=friends&kind=prayer')
            ->assertSee('<input type="hidden" name="wall_scope" value="friends">', false)
            ->assertSee('<input type="hidden" name="wall_kind" value="prayer">', false);
    }

    public function test_write_prayer_button_prechecks_prayer(): void
    {
        $me = $this->member('我');
        $page = $this->actingAs($me)->get(route('diaries.create', ['kind' => 'prayer']))->assertSee('寫代禱事項')->getContent();
        $this->assertMatchesRegularExpression('/id="is_prayer"[^>]*\bchecked\b/s', $page);
        $this->assertDoesNotMatchRegularExpression('/id="is_prayer"[^>]*\bchecked\b/s', $this->actingAs($me)->get(route('diaries.create'))->getContent());
        $this->actingAs($me)->get(route('diaries.create'))->assertSee('寫心情日記')->assertDontSee('寫代禱事項');
    }
}
