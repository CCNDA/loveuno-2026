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

    public function test_no_preferences_asks_to_set_them_first(): void
    {
        $me = $this->member('我', ['gender' => '男']);
        $this->member('一位姊妹');
        $this->list($me)->assertOk()->assertSee('請先設定您的交友條件')->assertSee('設定交友條件')
            ->assertSee(route('center.preferences'), false)
            ->assertDontSee('一位姊妹')->assertDontSee('用暱稱找')->assertDontSee('共 ');
        // 帶暱稱也一樣：條件沒設就不找
        $this->list($me, ['name' => '姊妹'])->assertSee('請先設定您的交友條件')->assertDontSee('一位姊妹');
    }

    // 小霏 11/4 實測：交友條件頁寫「沒填的就是不限」，四項都不限存檔後要列全部，不能卡在「請先設定」
    public function test_saving_all_unlimited_counts_as_set(): void
    {
        $me = $this->member('我', ['gender' => '男']);
        $this->member('一位姊妹');
        $this->member('一位弟兄', ['gender' => '男']);
        $this->list($me)->assertSee('請先設定您的交友條件');
        $this->actingAs($me)->put('/center/preferences', ['pref_gender' => '', 'pref_age_min' => '', 'pref_age_max' => '', 'pref_place' => ''])
            ->assertRedirect('/center/preferences');
        $me = $me->fresh(); // 測試裡同一個物件，profile 關聯還是存檔前那份；實際每個請求都會重讀
        // 11/5 熊叔叔「想認識…預設就是選擇異性」：其他都不拘，性別照個人資料配異性
        $this->list($me)->assertDontSee('請先設定您的交友條件')->assertSee('依您的交友條件列出：<strong>姊妹</strong>', false)->assertSee('一位姊妹')->assertDontSee('一位弟兄')->assertSee('用暱稱找');
        // 之後改回有條件也照條件列
        $this->actingAs($me)->put('/center/preferences', ['pref_gender' => '女']);
        $this->list($me->fresh())->assertSee('一位姊妹')->assertDontSee('一位弟兄');
    }

    public function test_self_not_listed(): void
    {
        $me = $this->member('我', ['gender' => '女', 'pref_gender' => '女']);
        $this->member('一位姊妹');
        $this->list($me)->assertSee('一位姊妹')->assertDontSee('">我</a>', false);
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

    // ── 交友條件補齊設計稿的欄位（熊叔叔 2026-11-05 TG）──

    public function test_default_is_opposite_gender_from_profile(): void
    {
        $brother = $this->member('弟兄我', ['gender' => '男', 'preferences_saved_at' => now()]);
        $sister = $this->member('姊妹我', ['gender' => '女', 'preferences_saved_at' => now()]);
        $this->member('一位姊妹');
        $this->member('一位弟兄', ['gender' => '男']);
        $this->list($brother)->assertSee('一位姊妹')->assertDontSee('一位弟兄');
        $this->list($sister)->assertSee('一位弟兄')->assertDontSee('一位姊妹');
        // 交友條件頁說明照性別寫
        $this->actingAs($brother)->get('/center/preferences')->assertSee('列出<strong>姊妹</strong>', false)->assertDontSee('id="pref_gender"', false);
        // 個人資料沒填性別：請他先填
        $nobody = $this->member('沒填性別', ['gender' => '']);
        $this->actingAs($nobody)->get('/center/preferences')->assertSee('請先到');
    }

    public function test_marital_faith_height_weight_filter_with_widening(): void
    {
        $me = $this->member('我', ['gender' => '男', 'pref_marital' => '未婚', 'pref_faith' => '基督徒',
            'pref_height_min' => 158, 'pref_height_max' => 162, 'pref_weight_min' => 48, 'pref_weight_max' => 52, 'preferences_saved_at' => now()]);
        $this->member('都符合', ['height' => 160, 'weight' => 50]);
        $this->member('離婚的', ['marital' => '離婚', 'height' => 160, 'weight' => 50]);
        $this->member('慕道友', ['faith' => '慕道友', 'height' => 160, 'weight' => 50]);
        $this->member('身高放寬下限', ['height' => 155, 'weight' => 50]); // 158 往下放寬到 155
        $this->member('身高放寬上限', ['height' => 164, 'weight' => 50]); // 162 往上放寬到 164
        $this->member('太高', ['height' => 165, 'weight' => 50]);
        $this->member('太矮', ['height' => 154, 'weight' => 50]);
        $this->member('體重放寬', ['height' => 160, 'weight' => 54]);   // 52 往上放寬到 54
        $this->member('太重', ['height' => 160, 'weight' => 55]);
        $this->member('沒填身高', ['height' => null, 'weight' => 50]);

        $this->list($me)->assertSee('都符合')->assertSee('身高放寬下限')->assertSee('身高放寬上限')->assertSee('體重放寬')
            ->assertDontSee('離婚的')->assertDontSee('慕道友')->assertDontSee('太高')->assertDontSee('太矮')->assertDontSee('太重')->assertDontSee('沒填身高')
            ->assertSee('未婚・基督徒・身高 158～162 公分・體重 48～52 公斤', false);
    }

    public function test_new_preference_fields_save_and_validate(): void
    {
        $me = $this->member('我', ['gender' => '男']);
        $this->actingAs($me)->put('/center/preferences', ['pref_marital' => '喪偶', 'pref_faith' => '慕道友',
            'pref_height_min' => 150, 'pref_height_max' => 170, 'pref_weight_min' => 40, 'pref_weight_max' => 60])->assertSessionHasNoErrors();
        $p = $me->profile->fresh();
        $this->assertSame(['喪偶', '慕道友', 150, 170, 40, 60], [$p->pref_marital, $p->pref_faith, $p->pref_height_min, $p->pref_height_max, $p->pref_weight_min, $p->pref_weight_max]);

        $this->actingAs($me)->put('/center/preferences', ['pref_marital' => '已婚'])->assertSessionHasErrors('pref_marital'); // 管理員專用的值不能選
        $this->actingAs($me)->put('/center/preferences', ['pref_faith' => '佛教'])->assertSessionHasErrors('pref_faith');
        $this->actingAs($me)->put('/center/preferences', ['pref_height_min' => 170, 'pref_height_max' => 160])->assertSessionHasErrors('pref_height_max');
        $this->actingAs($me)->put('/center/preferences', ['pref_weight_min' => 10])->assertSessionHasErrors('pref_weight_min');
        $this->actingAs($me)->put('/center/preferences', ['pref_height_max' => 170])->assertSessionHasNoErrors(); // 只填上限可以

        // 表單照設計稿的順序：對象年齡、婚姻狀態、對象信仰、居住地區、對象身高、對象體重
        $html = $this->actingAs($me)->get('/center/preferences')->getContent();
        $pos = array_map(fn ($t) => strpos($html, $t), ['對象年齡', '婚姻狀態', '對象信仰', '居住地區', '對象身高（cm）', '對象體重（kg）']);
        $this->assertNotContains(false, $pos);
        $sorted = $pos;
        sort($sorted);
        $this->assertSame($sorted, $pos);
        $this->assertStringContainsString('此欄不會顯示於交友檔案', $html);
    }

    // 小布 11/5 審查附註 3：個人資料沒填性別，認識朋友提醒他去填
    public function test_no_gender_hint_on_member_list(): void
    {
        $me = $this->member('沒填性別', ['gender' => '', 'preferences_saved_at' => now()]);
        $this->list($me)->assertSee('就會自動幫您列出異性');
        $he = $this->member('弟兄', ['gender' => '男', 'preferences_saved_at' => now()]);
        $this->list($he)->assertDontSee('就會自動幫您列出異性');
    }
}
