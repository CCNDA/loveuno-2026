<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberListTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, string $gender, string $place, int $year = 1990): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => $gender, 'live_place' => $place, 'birth_year' => $year]);

        return $u;
    }

    public function test_guest_cannot_see_member_list(): void
    {
        $this->get('/members')->assertRedirect('/login');
    }

    public function test_list_shows_members_with_age_group_not_birth_year(): void
    {
        $viewer = $this->member('看的人', '男', '台北市');
        $this->member('晨光', '女', '台中市', 1994);

        $this->actingAs($viewer)->get('/members')->assertOk()
            ->assertSee('晨光')->assertSee('歲')->assertDontSee('1994')
            ->assertSee('共 2 位');
    }

    public function test_filters_by_gender_and_place(): void
    {
        $viewer = $this->member('看的人', '男', '台北市');
        $this->member('甲姊妹', '女', '台中市');
        $this->member('乙姊妹', '女', '高雄市');
        $this->member('丙弟兄', '男', '台中市');

        $this->actingAs($viewer)->get('/members?gender=女&place=台中市')->assertOk()
            ->assertSee('甲姊妹')->assertDontSee('乙姊妹')->assertDontSee('丙弟兄')
            ->assertSee('共 1 位');
    }

    public function test_invalid_gender_is_rejected(): void
    {
        $viewer = $this->member('看的人', '男', '台北市');
        $this->actingAs($viewer)->from('/members')->get('/members?gender=x')->assertSessionHasErrors('gender');
        // 回到列表時要看得到錯誤（小布 0.3.0 複測：原本沒有任何提示）
        $this->actingAs($viewer)->followingRedirects()->from('/members')->get('/members?gender=x')
            ->assertOk()->assertSee('篩選條件有問題')->assertSee('性別只能選弟兄或姊妹');
    }

    public function test_avatar_is_hidden_from_screen_readers(): void
    {
        $viewer = $this->member('看的人', '男', '台北市');
        $this->actingAs($viewer)->get('/members')->assertSee('class="avatar avatar-md"', false)->assertSee('aria-hidden="true"', false);
    }
}
