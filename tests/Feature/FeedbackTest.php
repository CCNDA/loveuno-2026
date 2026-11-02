<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// 回報問題／建議（熊叔叔 11/2 TG 選 fb-A；兩面審查 11/2：每一頁、沒登入也能送）
class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_guest_and_unverified_member_can_send_honeypot_blocks_bots(): void
    {
        // 沒登入：登入頁下面就有，開新分頁
        $this->get('/login')->assertOk()->assertSee(route('feedback.create', ['from' => '/login']), false)->assertSee('target="_blank"', false);
        $this->get('/feedback?from=/login')->assertOk()->assertSee('Email（可以不填）');
        $this->post('/feedback', ['kind' => 'problem', 'body' => '忘記密碼收不到信', 'email' => 'Old.Member@Example.com', 'page' => '/login'])
            ->assertRedirect('/login');
        $g = Feedback::firstOrFail();
        $this->assertNull($g->user_id);
        $this->assertSame('old.member@example.com', $g->email);
        // 機器人填了看不到的那格：裝作收到，不存
        $this->post('/feedback', ['kind' => 'problem', 'body' => '買便宜的藥', 'website' => 'http://spam.example'])->assertRedirect(route('home'));
        $this->assertSame(1, Feedback::count());

        // 手機還沒驗證（註冊卡住的人最需要回報）
        $stuck = $this->member('卡住的人', ['phone_verified_at' => null]);
        $this->actingAs($stuck)->post('/feedback', ['kind' => 'problem', 'body' => '收不到簡訊認證碼', 'page' => '/phone/verify', 'email' => 'x@y.z'])
            ->assertRedirect('/phone/verify');
        $f = Feedback::where('user_id', $stuck->id)->firstOrFail();
        $this->assertSame('', $f->email); // 登入的不另外記 email
    }

    public function test_records_page_device_screen_context_and_error_pages_link(): void
    {
        $m = $this->member('小芯');
        $this->actingAs($m)->get('/members?gender=' . urlencode('女'))->assertSee(route('feedback.create', ['from' => '/members?gender=' . urlencode('女')]), false);
        $this->actingAs($m)->get('/feedback?from=' . urlencode('/members?gender=女'))->assertSee('value="/members?gender=女"', false)
            ->assertDontSee('Email（可以不填）');

        $this->actingAs($m)->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148 Line/14.0')
            ->post('/feedback', ['kind' => 'idea', 'body' => '希望可以用地區找人', 'page' => '/members', 'screen' => '390x844'])
            ->assertRedirect('/members')->assertSessionHas('status');
        $f = Feedback::firstOrFail();
        $this->assertSame([$m->id, 'idea', '希望可以用地區找人', '/members', '390x844', 'open'], [$f->user_id, $f->kind, $f->body, $f->page, $f->screen, $f->status]);
        $this->assertSame('手機・LINE 內建瀏覽器・390x844', $f->deviceLabel());
        $this->assertStringContainsString('版本 ', $f->context);
        $this->assertStringContainsString('字體 medium', $f->context);

        // 錯誤頁也能回報
        $this->get('/no-such-page-xyz')->assertNotFound()->assertSee('找不到這一頁')->assertSee(route('feedback.create', ['from' => '/no-such-page-xyz']), false);
    }

    public function test_only_own_site_paths_and_validation(): void
    {
        $m = $this->member();
        foreach (['https://evil.example/x', '//evil.example/x', '/\\evil.example', 'javascript:alert(1)'] as $bad) {
            // page、screen 是頁面自己填的：不對就不記，不報錯
            $this->actingAs($m)->post('/feedback', ['kind' => 'other', 'body' => '外部網址測試', 'page' => $bad, 'screen' => '<b>'])
                ->assertRedirect(route('home'))->assertSessionHasNoErrors();
        }
        $this->assertSame(4, Feedback::where('page', '')->where('screen', '')->count());
        $this->actingAs($m)->get('/feedback?from=' . urlencode('https://evil.example'))->assertDontSee('value="https://evil.example"', false);

        $this->actingAs($m)->post('/feedback', ['kind' => '', 'body' => '短'])->assertSessionHasErrors(['kind', 'body']);
        $this->actingAs($m)->post('/feedback', ['kind' => 'hack', 'body' => str_repeat('長', 1001)])->assertSessionHasErrors(['kind', 'body']);
        $this->post('/feedback', ['kind' => 'idea', 'body' => '沒登入亂填 email', 'email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    public function test_admin_filters_counts_export_status_and_screenshot(): void
    {
        Storage::fake('avatars');
        $m = $this->member('回報的人');
        $other = $this->member('別人');
        $admin = $this->member('管理員', ['is_admin' => true]);
        $this->actingAs($m)->post('/feedback', ['kind' => 'problem', 'body' => '按送禮物沒有反應', 'page' => '/members',
            'screenshot' => UploadedFile::fake()->image('shot.png', 800, 600)])->assertRedirect('/members');
        $this->actingAs($m)->post('/feedback', ['kind' => 'idea', 'body' => '=HYPERLINK("http://x")', 'page' => '/']);
        $this->actingAs($other)->post('/feedback', ['kind' => 'idea', 'body' => '別人的建議內容', 'page' => '/']);
        $f = Feedback::where('kind', 'problem')->firstOrFail();
        Storage::disk('avatars')->assertExists($f->screenshot_path);

        $this->actingAs($m)->get(route('admin.feedback'))->assertForbidden();
        $this->actingAs($m)->get(route('admin.feedback.export'))->assertForbidden();
        $this->actingAs($m)->get(route('admin.feedback.screenshot', $f))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.feedback.screenshot', $f))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($admin)->get(route('admin.feedback'))->assertOk()->assertSee('回報（3')->assertSee('他送過 2 則')
            ->assertSee('按送禮物沒有反應')->assertSee('看截圖')->assertDontSee('<a href="/members"', false); // 頁面只是文字
        $this->actingAs($admin)->get(route('admin.feedback', ['kind' => 'problem']))->assertSee('按送禮物沒有反應')->assertDontSee('別人的建議內容');
        $this->actingAs($admin)->get(route('admin.feedback', ['user' => $other->id]))->assertSee('別人的建議內容')->assertDontSee('按送禮物沒有反應');

        // 匯出：Excel 公式要被擋（前面加 '）
        $csv = $this->actingAs($admin)->get(route('admin.feedback.export'))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('按送禮物沒有反應', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);

        $this->actingAs($admin)->post(route('admin.feedback.status', $f), ['status' => 'done']);
        $this->assertSame('done', $f->fresh()->status);
        $this->assertSame($admin->id, $f->fresh()->handled_by);
        $this->actingAs($admin)->get(route('admin.feedback', ['show' => 'done']))->assertSee('按送禮物沒有反應')->assertSee('改回還沒處理');
        $this->actingAs($admin)->get(route('admin.feedback'))->assertDontSee('按送禮物沒有反應');
        $this->actingAs($admin)->post(route('admin.feedback.status', $f), ['status' => 'deleted'])->assertSessionHasErrors('status');
        $this->actingAs($m)->post(route('admin.feedback.status', $f), ['status' => 'open'])->assertForbidden();
    }

    public function test_account_deletion_removes_feedback_and_screenshots(): void
    {
        Storage::fake('avatars');
        $m = $this->member('要註銷的人');
        $this->actingAs($m)->post('/feedback', ['kind' => 'problem', 'body' => '截圖裡有別人的資料', 'screenshot' => UploadedFile::fake()->image('s.png', 400, 300)]);
        $path = Feedback::firstOrFail()->screenshot_path;
        Storage::disk('avatars')->assertExists($path);

        $this->actingAs($m)->delete(route('settings.destroy'), ['current_password' => 'password', 'reason' => '測試註銷時回報也要刪掉', 'confirm' => '1']);
        $this->assertNotNull($m->fresh()->deleted_at);
        $this->assertSame(0, Feedback::count());
        Storage::disk('avatars')->assertMissing($path);
    }
}
