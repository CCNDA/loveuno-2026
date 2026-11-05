<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 側邊選單照設計稿 Figma 1156:11227（熊叔叔 2026-11-05「figma 應該有規劃選單的結構」）
class SideMenuTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'live_place' => '台北市']);

        return $u;
    }

    public function test_side_menu_items_in_design_order(): void
    {
        $me = $this->member('雪莉');
        $html = $this->actingAs($me)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('aria-controls="side-menu"', $html);
        // 電腦版固定在左邊（熊叔叔 11/5）：body 有 has-side-menu；對話框 role 只在抽屜打開時由 JS 加
        $this->assertStringContainsString('class="has-bottom-nav has-side-menu"', $html);
        $this->assertStringNotContainsString('role="dialog"', $html);
        $menu = substr($html, strpos($html, 'id="side-menu"'));
        $items = ['生活日記牆', '我的日記', '認識朋友', '傳紙條', '約會', '愛的見證', '關於愛諾園', '支持奉獻', '聯絡我們', '常見問題', '會員條款', '登出'];
        $pos = array_map(fn ($t) => strpos($menu, '>' . $t), $items);
        $this->assertNotContains(false, $pos, '缺項目');
        $sorted = $pos;
        sort($sorted);
        $this->assertSame($sorted, $pos, '順序跟設計稿不一樣');
        $this->assertStringContainsString('雪莉', $menu);
        $this->assertStringContainsString('點<span class="visually-hidden">（愛積點）</span>', $menu);
        $this->assertStringNotContainsString('會員管理', $menu); // 一般會員看不到管理
    }

    public function test_admin_section_only_for_admins_and_guest_has_no_menu(): void
    {
        $admin = $this->member('管理員', ['is_admin' => true]);
        $menu = substr($this->actingAs($admin)->get('/')->getContent(), 0);
        $this->assertStringContainsString('<h2 class="side-menu-sub">管理</h2>', $menu);
        auth()->logout();
        $this->get('/login')->assertDontSee('id="side-menu"', false)->assertDontSee('menu-toggle', false);
    }

    public function test_my_diaries_lists_only_mine(): void
    {
        $me = $this->member('我');
        $other = $this->member('別人');
        $me->diaries()->create(['title' => '我寫的', 'body' => '內容', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $other->diaries()->create(['title' => '別人寫的', 'body' => '內容', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $this->actingAs($me)->get('/diaries?mine=1')->assertSee('我的日記')->assertSee('我寫的')->assertDontSee('別人寫的');
        $this->actingAs($me)->get('/diaries')->assertSee('我寫的')->assertSee('別人寫的');
    }
}
