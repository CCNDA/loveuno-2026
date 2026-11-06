<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 熊叔叔 11/6 決定 #7：送禮物、約會邀請、傳情書改成個人首頁上跳出的對話框（設計稿 328:7030、342:51697、235:7660）
class MemberDialogTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        return $u;
    }

    public function test_dialogs_on_profile_follow_friend_steps(): void
    {
        [$a, $b] = [$this->member('甲'), $this->member('乙')];

        $html = $this->actingAs($a)->get("/members/{$b->id}")->assertOk()->getContent();
        $this->assertStringContainsString('id="dlg-gift"', $html);
        $this->assertStringContainsString('data-dialog="gift"', $html);
        $this->assertStringContainsString('id="dlg-date"', $html);
        $this->assertStringContainsString('要先跟 乙 成為「聊天好友」才能邀請約會', $html);
        $this->assertStringNotContainsString('id="dlg-note"', $html);   // 還沒互送禮物：沒有傳情書
        $this->assertStringNotContainsString('data-open', $html);
        $this->assertSame(1, substr_count($html, 'id="g-item-1"'));    // 對話框的欄位有自己的 id，不跟別的撞

        $this->befriend($a, $b);
        $html = $this->actingAs($a)->get("/members/{$b->id}")->getContent();
        $this->assertStringContainsString('id="dlg-note"', $html);
        $this->assertStringContainsString('data-dialog="note"', $html);

        // 自己的檔案頁沒有對話框
        $this->actingAs($a)->get("/members/{$a->id}")->assertDontSee('id="dlg-gift"', false);
    }

    public function test_failed_submit_reopens_only_that_dialog(): void
    {
        [$a, $b] = [$this->member('甲'), $this->member('乙')];
        $this->befriend($a, $b);

        // 送禮物沒選禮物：回到檔案頁，只有送禮物對話框打開、帶錯誤和剛剛寫的簡訊
        $this->actingAs($a)->from("/members/{$b->id}")
            ->post(route('gifts.store', $b), ['_dialog' => 'gift', 'message' => '剛剛寫的'])->assertRedirect("/members/{$b->id}");
        $html = $this->actingAs($a)->get("/members/{$b->id}")->getContent();
        $this->assertMatchesRegularExpression('/<dialog id="dlg-gift"[^>]*data-open/', $html);
        $this->assertDoesNotMatchRegularExpression('/<dialog id="dlg-(date|note)"[^>]*data-open/', $html);
        $this->assertStringContainsString('id="g-item-error"', $html);
        $this->assertStringContainsString('value="剛剛寫的"', $html);
        $this->assertStringNotContainsString('剛剛寫的</textarea>', $html);   // 不會跑到情書、約會的欄位

        // 情書沒寫內文：只有情書對話框打開
        $this->actingAs($a)->from("/members/{$b->id}")
            ->post(route('notes.store', $b), ['_dialog' => 'note', 'title' => '標題留著'])->assertRedirect("/members/{$b->id}");
        $html = $this->actingAs($a)->get("/members/{$b->id}")->getContent();
        $this->assertMatchesRegularExpression('/<dialog id="dlg-note"[^>]*data-open/', $html);
        $this->assertDoesNotMatchRegularExpression('/<dialog id="dlg-gift"[^>]*data-open/', $html);
        $this->assertStringContainsString('id="n-body-error"', $html);
        $this->assertStringContainsString('value="標題留著"', $html);
        $this->assertStringNotContainsString('id="g-item-error"', $html);
    }
}
