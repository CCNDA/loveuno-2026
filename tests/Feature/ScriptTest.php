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
        $this->get('/')->assertSee('嚴謹認證')->assertSee('lang="zh-Hant-TW"', false);

        $this->from('/')->get('/script/hans')->assertRedirect('/');
        $this->get('/')->assertSee('严谨认证')->assertSee('lang="zh-Hans"', false)->assertSee('繁體', false);

        $this->get('/script/hant')->assertRedirect();
        $this->get('/')->assertSee('嚴謹認證');
    }

    public function test_bad_value_and_offsite_back_are_ignored(): void
    {
        $this->get('/script/xx')->assertNotFound();
        // 回上一頁只回本站，不跟著外站 Referer 走
        $this->withHeader('Referer', 'https://evil.example/')->get('/script/hans')->assertRedirect('/');
    }

    public function test_ui_terms_only_in_interface_elements(): void
    {
        // 介面元素：換成大陸用詞
        $out = ToSimplified::html('<nav><a href="/login">登入</a><a href="/logout">登出</a></nav><button>儲存設定</button>'
            . '<label>電子信箱</label><input placeholder="搜尋會員"><table><tr><th>積點</th></tr></table><span data-ui-terms>我的相簿</span>');
        $this->assertStringContainsString('>登录</a>', $out);
        $this->assertStringContainsString('>退出登录</a>', $out); // 登出→退出登录，不會再被「登入」那條改一次
        $this->assertStringContainsString('<button>保存设置</button>', $out);
        $this->assertStringContainsString('<label>电子邮箱</label>', $out);
        $this->assertStringContainsString('placeholder="搜索会员"', $out);
        $this->assertStringContainsString('<th>积分</th>', $out);
        $this->assertStringContainsString('>我的相册</span>', $out);

        // 會員內容：只轉字、不改詞
        $out = ToSimplified::html('<p class="diary">今天登入網路，看到相簿裡的照片，想封鎖那個人。</p><h2>我的個人檔案</h2>');
        $this->assertStringContainsString('今天登入网路，看到相簿里的照片，想封锁那个人。', $out);
        $this->assertStringContainsString('<h2>我的个人档案</h2>', $out);
    }

    public function test_ui_terms_keep_form_values(): void
    {
        // 按鈕裡的 value、textarea 照舊不動（保護區比用詞對照先處理）
        $out = ToSimplified::html('<button name="a" value="登入">登入</button><textarea>登入網路</textarea>');
        $this->assertStringContainsString('value="登入"', $out);
        $this->assertStringContainsString('>登录</button>', $out);
        $this->assertStringContainsString('<textarea>登入網路</textarea>', $out);
    }

    public function test_more_protection_cases(): void
    {
        // 私用區字元要用雙引號字串才會變成真的字元
        $out = ToSimplified::html('<option value=繁體>繁體</option><input value=\'單引號\'><p>內容裡有 ' . "\u{E000}0\u{E001}" . ' 這種字</p><textarea>不要動</textarea>');
        $this->assertStringContainsString('value=繁體', $out);
        $this->assertStringContainsString('>繁体</option>', $out);
        $this->assertStringContainsString("value='單引號'", $out);
        $this->assertStringContainsString("\u{E000}0\u{E001}", $out); // 內容裡的私用區字元原樣留著，沒被換成別的區塊
        $this->assertStringContainsString('<textarea>不要動</textarea>', $out);
    }

    public function test_referer_lookalikes_go_home_and_json_untouched(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        foreach (['//evil.example/x', "https://{$host}.evil.example/", 'javascript:alert(1)'] as $ref) {
            $this->withHeader('Referer', $ref)->get('/script/hans')->assertRedirect('/');
        }
    }

    public function test_middleware_leaves_non_html_alone(): void
    {
        $req = \Illuminate\Http\Request::create('/x');
        $session = $this->app['session']->driver();
        $session->put('script', 'hans');
        $req->setLaravelSession($session);
        $mw = new \App\Http\Middleware\ConvertScript;

        $json = $mw->handle($req, fn () => response()->json(['name' => '繁體']));
        $this->assertStringContainsString('\u7e41\u9ad4', $json->getContent()); // JSON 原樣（json_encode 會跳脫成 \u）
        $html = $mw->handle($req, fn () => response('<p>繁體</p>', 200, ['Content-Type' => 'text/html; charset=UTF-8']));
        $this->assertSame('<p>繁体</p>', $html->getContent());
    }

    public function test_editing_in_simplified_mode_does_not_rewrite_stored_text(): void
    {
        $u = User::factory()->create(['name' => '繁體暱稱']);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'intro' => '我喜歡讀經']);
        $this->actingAs($u)->get('/script/hans');

        $html = $this->actingAs($u)->get('/me/edit')->getContent();
        $this->assertStringContainsString('value="繁體暱稱"', $html);      // 表單值不轉
        $this->assertStringContainsString('个人资料', $html);              // 畫面文字轉了
        $html = $this->actingAs($u)->get('/me/intro')->getContent();
        $this->assertStringContainsString('>我喜歡讀經</textarea>', $html);
        $this->assertStringContainsString('自我介绍', $html);
    }
}
