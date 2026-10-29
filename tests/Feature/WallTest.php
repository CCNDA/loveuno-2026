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
}
