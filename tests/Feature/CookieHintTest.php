<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

// 登入時瀏覽器沒收 cookie，直接提示（Asana 1220224661011603，熊叔叔 11/5 選 C1）
class CookieHintTest extends TestCase
{
    use RefreshDatabase;

    private const MSG = '您的瀏覽器沒有開啟 cookie';

    public function test_login_page_has_hidden_cookie_warning_and_script(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('id="cookie-warn"', false)
            ->assertSee(self::MSG)
            ->assertSee('js/cookie-check.js', false);
        $this->assertFileExists(public_path('js/cookie-check.js'));
    }

    public function test_419_keeps_expired_message_and_lets_browser_decide_cookie(): void
    {
        Route::middleware('web')->get('/_test419', fn () => abort(419));
        // 有開 cookie、但 session cookie 已過期（請求不帶 session cookie）→ 還是「頁面放太久了」（小布 11/23 審查）
        $this->get('/_test419')->assertStatus(419)->assertSee('頁面放太久了')
            ->assertSee('id="cookie-warn" role="alert" hidden', false)  // cookie 提示藏著，交給 cookie-check.js
            ->assertSee('js/cookie-check.js', false);
        // 有帶 session cookie：一樣
        $this->withCookie(config('session.cookie'), 'x')->get('/_test419')
            ->assertStatus(419)->assertSee('頁面放太久了')->assertSee('id="cookie-warn" role="alert" hidden', false);
    }
}
