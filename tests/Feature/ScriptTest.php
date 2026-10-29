<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Support\ToSimplified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_conversion_uses_phrases_and_characters(): void
    {
        $this->assertSame('爱诺园会员', ToSimplified::text('愛諾園會員'));
        $this->assertSame('头发', ToSimplified::text('頭髮')); // 詞：髮→发（不是 發）
        $this->assertSame('出发', ToSimplified::text('出發'));
    }

    public function test_html_keeps_form_values_and_scripts(): void
    {
        $html = '<p>愛諾園</p><input value="繁體暱稱"><textarea name="intro">自我介紹</textarea><script>var a="繁體";</script>';
        $out = ToSimplified::html($html);
        $this->assertStringContainsString('<p>爱诺园</p>', $out);
        $this->assertStringContainsString('value="繁體暱稱"', $out);
        $this->assertStringContainsString('>自我介紹</textarea>', $out);
        $this->assertStringContainsString('var a="繁體";', $out);
    }

    public function test_toggle_switches_whole_page_and_back(): void
    {
        $this->get('/')->assertSee('在信仰裡，認識另一半')->assertSee('lang="zh-Hant-TW"', false);

        $this->from('/')->get('/script/hans')->assertRedirect('/');
        $this->get('/')->assertSee('在信仰里，认识另一半')->assertSee('lang="zh-Hans"', false)->assertSee('繁體', false);

        $this->get('/script/hant')->assertRedirect();
        $this->get('/')->assertSee('在信仰裡，認識另一半');
    }

    public function test_bad_value_and_offsite_back_are_ignored(): void
    {
        $this->get('/script/xx')->assertNotFound();
        // 回上一頁只回本站，不跟著外站 Referer 走
        $this->withHeader('Referer', 'https://evil.example/')->get('/script/hans')->assertRedirect('/');
    }

    public function test_editing_in_simplified_mode_does_not_rewrite_stored_text(): void
    {
        $u = User::factory()->create(['name' => '繁體暱稱']);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'intro' => '我喜歡讀經']);
        $this->actingAs($u)->get('/script/hans');

        $html = $this->actingAs($u)->get('/me/edit')->getContent();
        $this->assertStringContainsString('value="繁體暱稱"', $html);      // 表單值不轉
        $this->assertStringContainsString('>我喜歡讀經</textarea>', $html);
        $this->assertStringContainsString('编辑个人档案', $html);          // 畫面文字轉了
    }
}
