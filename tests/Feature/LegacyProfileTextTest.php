<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Support\LegacyText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// 熊叔叔 11/6 TG：自我介紹顯示出「<br>」、最愛經句只有出處沒有經文
class LegacyProfileTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_converts_br_and_entities(): void
    {
        $this->assertSame("字：常順\n\n我的人生觀\n人生 五大", LegacyText::plain('字：常順<br><br>我的人生觀<BR />人生&nbsp;五大'));
        $this->assertSame('沒有標籤', LegacyText::plain('<b>沒有</b>標籤'));
        $this->assertNull(LegacyText::plain(null));
    }

    public function test_command_cleans_intro_and_fills_verse_text(): void
    {
        DB::table('bible_verses')->insert(['book' => 1, 'chapter' => 1, 'verse' => 1, 'text' => '起初，神創造天地。']);
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'gender' => '男', 'birth_year' => 1980, 'intro' => '第一行<br>第二行', 'verse_book' => '創世記', 'verse_chapter' => 1, 'verse_verse' => 1, 'verse_text' => '']);

        $this->artisan('profiles:fix-legacy-text', ['--dry-run' => true])->expectsOutputToContain('自我介紹去 HTML：1 筆；補最愛經句經文：1 筆')->assertSuccessful();
        $this->assertSame('第一行<br>第二行', Profile::find($u->id)->intro); // 乾跑不改

        $this->artisan('profiles:fix-legacy-text')->assertSuccessful();
        $p = Profile::find($u->id);
        $this->assertSame("第一行\n第二行", $p->intro);
        $this->assertSame('起初，神創造天地。', $p->verse_text);
        $this->actingAs($u)->get(route('members.show', ['user' => $u, 'tab' => 'intro']))->assertSee('起初，神創造天地。')->assertDontSee('&lt;br&gt;', false);
    }
}
