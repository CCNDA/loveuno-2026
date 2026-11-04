<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 認識朋友照「交友條件」列出、只能用暱稱找（熊叔叔 11/4 TG：「列表應該是他設定的對象」「頂多搜尋名字」
// 「如果沒有合適的對象，請他回去更改交友條件」）。取代 0.31.0 的條件搜尋（D-01）。
class MemberSearchTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, array $profile = [], array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        // $profile 放左邊：PHP 的 + 是左邊優先，要讓傳進來的值蓋掉預設值
        Profile::forceCreate($profile + ['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'live_place' => '台北市', 'marital' => '未婚', 'faith' => '基督徒']);

        return $u;
    }

    private function list(User $me, array $q = [])
    {
        return $this->actingAs($me)->get('/members' . ($q ? '?' . http_build_query($q) : ''));
    }

    public function test_list_follows_preferences_with_age_widened_to_five_year_groups(): void
    {
        $y = (int) now()->format('Y');
        // 交友條件：姊妹、27～31 歲、台北市 → 年齡放寬成 25～34（跟卡片的年齡層一樣，試不出出生年）
        $me = $this->member('我', ['gender' => '男', 'pref_gender' => '女', 'pref_age_min' => 27, 'pref_age_max' => 31, 'pref_place' => '台北市']);
        $this->member('台北三十歲姊妹', ['birth_year' => $y - 30]);
        $this->member('二十六歲姊妹', ['birth_year' => $y - 26]);   // 27 往下放寬到 25
        $this->member('三十四歲姊妹', ['birth_year' => $y - 34]);   // 31 往上放寬到 34
        $this->member('三十五歲姊妹', ['birth_year' => $y - 35]);
        $this->member('二十四歲姊妹', ['birth_year' => $y - 24]);
        $this->member('台北三十歲弟兄', ['gender' => '男', 'birth_year' => $y - 30]);
        $this->member('台中三十歲姊妹', ['birth_year' => $y - 30, 'live_place' => '台中市']);

        $this->list($me)->assertOk()
            ->assertSee('依您的交友條件列出')->assertSee('姊妹・27～31 歲・台北市')->assertSee('年齡以 5 歲一格計')->assertSee('修改交友條件')
            ->assertSee('台北三十歲姊妹')->assertSee('二十六歲姊妹')->assertSee('三十四歲姊妹')
            ->assertDontSee('三十五歲姊妹')->assertDontSee('二十四歲姊妹')
            ->assertDontSee('台北三十歲弟兄')->assertDontSee('台中三十歲姊妹')
            ->assertDontSee('更多條件'); // 條件搜尋拿掉了
        // 網址帶舊的條件參數也不理會：列表只看交友條件
        $this->list($me, ['gender' => '男', 'place' => '台中市', 'age_min' => 40])
            ->assertSee('台北三十歲姊妹')->assertDontSee('台北三十歲弟兄')->assertDontSee('台中三十歲姊妹');
    }

    public function test_only_age_floor_or_ceiling(): void
    {
        $y = (int) now()->format('Y');
        $me = $this->member('我', ['gender' => '男', 'pref_age_min' => 41]);
        $this->member('四十歲', ['birth_year' => $y - 40]);
        $this->member('三十九歲', ['birth_year' => $y - 39]);
        $this->list($me)->assertSee('41 歲以上')->assertSee('四十歲')->assertDontSee('三十九歲');

        $me2 = $this->member('我二', ['gender' => '男', 'pref_age_max' => 18]);
        $this->member('十九歲', ['birth_year' => $y - 19]);
        $this->member('二十歲', ['birth_year' => $y - 20]);
        $this->list($me2)->assertSee('18 歲以下')->assertSee('十九歲')->assertDontSee('二十歲');
    }

    public function test_no_preferences_lists_everyone_and_asks_to_set_them(): void
    {
        $me = $this->member('我', ['gender' => '男']);
        $this->member('一位姊妹');
        $this->member('一位弟兄', ['gender' => '男']);
        $this->list($me)->assertSee('您還沒設定交友條件')->assertSee('設定交友條件')
            ->assertSee(route('center.preferences'), false)
            ->assertSee('一位姊妹')->assertSee('一位弟兄')
            ->assertDontSee('">我</a>', false); // 自己不列
    }

    public function test_nobody_matches_asks_to_change_preferences(): void
    {
        $me = $this->member('我', ['gender' => '男', 'pref_gender' => '女', 'pref_place' => '花蓮縣']);
        $this->member('台北姊妹');
        $this->list($me)->assertSee('共 0 位')->assertSee('目前沒有符合您交友條件的會員')
            ->assertSee('回去修改交友條件')->assertDontSee('台北姊妹');
    }

    public function test_name_search_within_preferences(): void
    {
        $me = $this->member('我', ['gender' => '男', 'pref_gender' => '女']);
        $this->member('晨光');
        $this->member('晨星', ['gender' => '男']); // 不是交友條件的對象：用暱稱也找不到
        $this->member('小芯百分比%');
        $this->list($me, ['name' => '晨'])->assertSee('晨光')->assertDontSee('晨星')->assertSee('清除');
        // % 是一般字元，不是萬用字元
        $this->list($me, ['name' => '%'])->assertSee('小芯百分比%')->assertDontSee('晨光');
        $this->list($me, ['name' => '沒有這個人'])->assertSee('沒有暱稱有「沒有這個人」的');
        // 太長的暱稱：擋下來、顯示錯誤
        $this->list($me, ['name' => str_repeat('長', 21)])->assertSessionHasErrors('name');
    }

    public function test_married_inactive_not_listed(): void
    {
        $me = $this->member('我', ['gender' => '男', 'pref_gender' => '女']);
        $wed = $this->member('結婚的人', ['marital' => '已婚']); // 熊叔叔 11/2 TG 選甲
        $this->member('暫停的人', [], ['is_active' => false]);
        $this->member('照常的人');
        $this->list($me)->assertSee('照常的人')->assertDontSee('結婚的人')->assertDontSee('暫停的人');
        $this->actingAs($me)->get("/members/{$wed->id}")->assertOk(); // 檔案照常看得到
    }
}
