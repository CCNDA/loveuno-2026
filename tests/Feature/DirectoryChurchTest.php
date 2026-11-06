<?php

namespace Tests\Feature;

use App\Models\DirectoryChurch;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 熊叔叔 11/6 決定 #11（甲）：名錄每天匯出公開教會清單，愛諾園每天抓一次存自己的表；自我介紹先選國家再挑教會，找不到可以自己打
class DirectoryChurchTest extends TestCase
{
    use RefreshDatabase;

    private function feed(array $orgs): string
    {
        $path = tempnam(sys_get_temp_dir(), 'church') . '.json';
        file_put_contents($path, json_encode($orgs, JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function org(string $og, string $oc, string $on, string $cc, string $co, string $cy, string $tn = ''): array
    {
        return ['og' => $og, 'oc' => $oc, 'on' => $on, 'cc' => $cc, 'co' => $co, 'cy' => $cy, 'tn' => $tn, 'em' => 'x@example.org'];
    }

    public function test_sync_keeps_only_churches_and_refuses_short_feed(): void
    {
        $file = $this->feed([
            '1001' => $this->org('教會', '台北示範長老教會', '台北示範長老教會', 'TW', '台灣', '台北市', '大安區'),
            '1002' => $this->org('教會', '台灣基督長老教會一心教會', '高雄一心長老教會', 'TW', '台灣', '高雄市'),
            '1003' => $this->org('社會關懷', '某某基金會', '某某基金會', 'TW', '台灣', '台北市'),
            '1004' => $this->org('教會', 'Grace Church', 'Grace Church', 'US', '美國', 'California'),
        ]);
        $this->artisan('directory:sync-churches', ['--file' => $file, '--min' => 1])->assertSuccessful();
        $this->assertSame(3, DirectoryChurch::count());
        $this->assertNull(DirectoryChurch::find(1003));                       // 不是教會的不收
        $this->assertSame('高雄一心長老教會', DirectoryChurch::find(1002)->alias); // 常用名稱跟全名不同時留著
        $this->assertSame('', DirectoryChurch::find(1001)->alias);
        $this->assertSame(['TW' => '台灣', 'US' => '美國'], DirectoryChurch::countries());

        // 名錄拿掉的教會，下次同步也拿掉
        $this->travel(1)->minutes();
        $this->artisan('directory:sync-churches', ['--file' => $this->feed(['1001' => $this->org('教會', '台北示範長老教會', '', 'TW', '台灣', '台北市')]), '--min' => 1])->assertSuccessful();
        $this->assertSame([1001], DirectoryChurch::pluck('oid')->all());

        // 筆數太少（例如名錄回了半份檔）：整批不動
        $this->artisan('directory:sync-churches', ['--file' => $file, '--min' => 5000])->assertFailed();
        $this->assertSame([1001], DirectoryChurch::pluck('oid')->all());
        $this->artisan('directory:sync-churches', ['--file' => $this->feed([]) . 'x-not-there', '--min' => 1])->assertFailed();
        $this->assertSame(1, DirectoryChurch::count());
    }

    public function test_search_and_save_church_from_directory_or_free_text(): void
    {
        $this->artisan('directory:sync-churches', ['--min' => 1, '--file' => $this->feed([
            '2001' => $this->org('教會', '台北示範長老教會', '台北示範長老教會', 'TW', '台灣', '台北市', '大安區'),
            '2002' => $this->org('教會', '新竹示範長老教會', '新竹示範長老教會', 'TW', '台灣', '新竹市'),
            '2003' => $this->org('教會', '示範長老教會 Los Angeles', '示範長老教會 Los Angeles', 'US', '美國', 'California'),
        ])]);
        $u = User::factory()->create(['name' => '甲']);
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        // 搜尋只在選的國家裡；開頭符合的排前面
        $rows = $this->actingAs($u)->getJson('/churches/search?country=TW&q=示範')->assertOk()->json();
        $this->assertSame([2001, 2002], collect($rows)->pluck('oid')->sort()->values()->all());
        $this->assertSame('台北市 大安區', collect($rows)->firstWhere('oid', 2001)['place']);
        $this->assertSame([2002], collect($this->getJson('/churches/search?country=TW&q=新竹')->json())->pluck('oid')->all());
        $this->assertSame([], $this->getJson('/churches/search?country=TW&q=')->json());

        // 從名錄挑（建議後面帶了地點也一樣）：存名錄編號、國家，名稱換回名錄全名
        $form = $this->introForm(['church_country' => 'TW', 'church' => '台北示範長老教會（台北市 大安區）', 'church_oid' => 2001, 'church_visible' => 1]);
        $this->actingAs($u)->put(route('profile.intro.update'), $form)->assertSessionHasNoErrors();
        $p = $u->profile->fresh();
        $this->assertSame([2001, 'TW', '台北示範長老教會'], [$p->church_oid, $p->church_country, $p->church]);

        // 自己打的（名錄找不到）：照存名稱，名錄編號空白
        $this->actingAs($u)->put(route('profile.intro.update'), $this->introForm(['church_country' => 'TW', 'church' => '我家附近的小教會', 'church_oid' => 2001]));
        $p = $u->profile->fresh();
        $this->assertSame([null, 'TW', '我家附近的小教會'], [$p->church_oid, $p->church_country, $p->church]);

        // 自動存檔只送名稱：國家、編號從資料庫補，一起存（改了名稱，名錄編號就不留）
        $this->actingAs($u)->patchJson(route('profile.intro.patch'), ['church' => '新竹示範長老教會', 'church_oid' => 2002])->assertOk();
        $p = $u->profile->fresh();
        $this->assertSame([2002, 'TW', '新竹示範長老教會'], [$p->church_oid, $p->church_country, $p->church]);

        // 必填的興趣、慣用語言 0 個不能存（小霏 11/6）
        $this->actingAs($u)->put(route('profile.intro.update'), array_diff_key($this->introForm(), ['hobbies' => 1]))->assertSessionHasErrors('hobbies');
        $this->actingAs($u)->put(route('profile.intro.update'), $this->introForm(['hobbies' => []]))->assertSessionHasErrors('hobbies');
        $this->actingAs($u)->put(route('profile.intro.update'), $this->introForm(['languages' => []]))->assertSessionHasErrors('languages');

        // 自我介紹頁：國家選單、教會建議、標籤輸入
        $html = $this->actingAs($u)->get(route('profile.intro'))->assertOk()->getContent();
        $this->assertStringContainsString('id="church_country"', $html);
        $this->assertStringContainsString('<option value="TW" selected>台灣</option>', $html);
        $this->assertStringContainsString('list="church-list"', $html);
        $this->assertSame(3, substr_count($html, 'data-tags="3"'));
    }
}
