<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_profile_fields_saved_and_shown(): void
    {
        $u = $this->member('我');
        $this->actingAs($u)->put('/me', $this->profileForm([
            'name' => '我', 'faith' => '基督徒', 'marital' => '未婚', 'children' => '0', 'education' => '大學', 'blood_type' => 'O',
            'height' => 165, 'weight' => 52, 'smoking' => '從來不抽', 'drinking' => '有需要才喝', 'family_planning' => '有',
            'real_name' => '王小明', 'home_phone' => '02-1234-5678',
        ]))->assertRedirect();
        $p = $u->profile->fresh();
        $this->assertSame(['基督徒', '未婚', '0', 165, '王小明'], [$p->faith, $p->marital, $p->children, $p->height, $p->real_name]);

        $other = $this->member('別人');
        $html = $this->actingAs($other)->get("/members/{$u->id}")->assertOk()->getContent();
        foreach (['基督徒', '未婚', '大學', '165 cm', '52 kg', '從來不抽', '有需要才喝'] as $t) {
            $this->assertStringContainsString($t, $html);
        }
        // 真實姓名、住家電話只有管理員看得到
        $this->assertStringNotContainsString('王小明', $html);
        $this->assertStringNotContainsString('1234-5678', $html);
        $this->assertArrayNotHasKey('real_name', $p->toArray());
        $admin = $this->member('管理員', ['is_admin' => true]);
        $this->actingAs($admin)->get("/members/{$u->id}")->assertSee('王小明')->assertSee('02-1234-5678');
    }

    public function test_profile_options_validated(): void
    {
        $u = $this->member();
        $this->actingAs($u)->put('/me', $this->profileForm(['name' => 'x', 'marital' => '已婚', 'blood_type' => 'Z', 'height' => 500, 'home_phone' => 'abc']))
            ->assertSessionHasErrors(['marital', 'blood_type', 'height', 'home_phone']);
    }

    public function test_intro_picks_limited_to_three_and_shown_on_intro_tab(): void
    {
        $u = $this->member('我');
        $this->actingAs($u)->put('/me/intro', $this->introForm(['hobbies' => ['各地旅遊', '看書寫作', '攝影繪畫', '讀經靈修']]))->assertSessionHasErrors(['hobbies' => '興趣最多選 3 種。']);
        $this->actingAs($u)->put('/me/intro', $this->introForm(['hobbies' => ['駭客']]))->assertSessionHasErrors('hobbies.0');
        $this->actingAs($u)->put('/me/intro', $this->introForm([
            'headline' => '愛爬山的老師', 'hobbies' => ['看書寫作', '各地旅遊'], 'sports' => ['登山'], 'languages' => ['中文/普通話', '台語/閩南話'],
            'verse_book' => '耶利米書', 'verse_chapter' => 29, 'verse_verse' => 11, 'verse_text' => '耶和華說：我知道我向你們所懷的意念…',
        ]))->assertRedirect();
        $this->assertSame(['看書寫作', '各地旅遊'], $u->profile->fresh()->hobbies); // 照選項順序存

        $other = $this->member('別人');
        $this->actingAs($other)->get("/members/{$u->id}?tab=intro")->assertOk()
            ->assertSee('愛爬山的老師')->assertSee('耶利米書 29:11')->assertSeeInOrder(['興趣嗜好', '看書寫作', '各地旅遊', '喜愛的運動', '登山', '慣用語言', '中文/普通話']);
        $this->actingAs($u)->put('/me/intro', $this->introForm(['verse_book' => '耶利米書']))->assertSessionHasErrors('verse_chapter');
        $this->actingAs($u)->put('/me/intro', $this->introForm(['verse_text' => '只有經文']))->assertSessionHasErrors(['verse_book' => '寫了經文，請一起選是哪一卷。']);
        // 選錯的興趣：錯誤摘要連到整組、欄位旁邊有訊息
        $this->actingAs($u)->put('/me/intro', $this->introForm(['hobbies' => ['駭客']]));
        $this->actingAs($u)->get('/me/intro')->assertSee('href="#hobbies"', false)->assertSee('id="hobbies-error"', false);
    }

    public function test_member_tabs_and_last_seen(): void
    {
        $u = $this->member('我');
        $u->forceFill(['last_login_at' => now()->subDays(3)])->save();
        $u->diaries()->create(['title' => '公開的', 'body' => 'b', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $u->diaries()->create(['title' => '不公開的', 'body' => 'b', 'visibility' => 'private', 'published_on' => now()->toDateString()]);
        $other = $this->member('別人');

        $this->actingAs($other)->get("/members/{$u->id}")->assertSee('最近登入：3 天前')->assertSeeInOrder(['個人資料', '自我介紹', '個人相簿', '心情日記']);
        $this->actingAs($other)->get("/members/{$u->id}?tab=diary")->assertSee('公開的')->assertDontSee('不公開的');
        $this->actingAs($other)->get("/members/{$u->id}?tab=album")->assertOk()->assertSee('個人相簿');
        $this->actingAs($other)->get("/members/{$u->id}?tab=hack")->assertOk(); // 不認得的當成個人資料
        // 沒有個人檔案的會員也看得到分頁
        $bare = User::factory()->create(['name' => '沒檔案']);
        $this->actingAs($other)->get("/members/{$bare->id}")->assertOk()->assertSee('心情日記');
    }
}
