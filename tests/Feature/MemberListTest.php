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
            ->assertSee('共 1 位'); // 自己不列
    }

    // 條件搜尋拿掉後（熊叔叔 11/4：照交友條件列出、只留暱稱），性別／居住地篩選改在 MemberSearchTest 測交友條件。
    // 這裡留著小布 0.3.0 複測的那條：輸入有錯回到列表時要看得到錯誤（原本沒有任何提示）。
    public function test_invalid_input_shows_error_on_list(): void
    {
        $viewer = $this->member('看的人', '男', '台北市');
        $long = str_repeat('長', 21);
        $this->actingAs($viewer)->from('/members')->get('/members?name=' . urlencode($long))->assertSessionHasErrors('name');
        $this->actingAs($viewer)->followingRedirects()->from('/members')->get('/members?name=' . urlencode($long))
            ->assertOk()->assertSee('暱稱有問題')
            // 錯誤摘要連到欄位、欄位指向錯誤說明、填的值留著（原本 0.3.0 那條也檢查這些）
            ->assertSee('href="#name"', false)->assertSee('aria-describedby="name-error"', false)->assertSee('value="' . $long . '"', false);
    }

    public function test_avatar_is_hidden_from_screen_readers(): void
    {
        $viewer = $this->member('看的人', '男', '台北市');
        $this->member('晨光', '女', '台中市'); // 自己不列，要有別人才有頭像
        $this->actingAs($viewer)->get('/members')->assertSee('class="avatar avatar-md"', false)->assertSee('aria-hidden="true"', false);
    }
}
