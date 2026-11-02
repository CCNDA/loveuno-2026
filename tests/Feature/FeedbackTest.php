<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// 回報問題／建議（熊叔叔 11/2 TG 選 fb-A）
class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_guest_must_log_in_but_unverified_member_can_send(): void
    {
        $this->get('/feedback')->assertRedirect(route('login'));
        $this->post('/feedback', ['kind' => 'problem', 'body' => '我是訪客'])->assertRedirect(route('login'));
        $this->assertSame(0, Feedback::count());

        // 手機還沒驗證（註冊卡住的人最需要回報）
        $stuck = $this->member('卡住的人', ['phone_verified_at' => null]);
        $this->actingAs($stuck)->get('/feedback?from=/phone/verify')->assertOk()->assertSee('回報問題／建議');
        $this->actingAs($stuck)->post('/feedback', ['kind' => 'problem', 'body' => '收不到簡訊認證碼', 'page' => '/phone/verify'])
            ->assertRedirect('/phone/verify');
        $this->assertSame(1, Feedback::where('user_id', $stuck->id)->count());
    }

    public function test_records_page_device_and_screen_and_footer_link(): void
    {
        $m = $this->member('小芯');
        // 每一頁下面都有按鈕，帶著現在這一頁
        $this->actingAs($m)->get('/members?gender=' . urlencode('女'))->assertSee(route('feedback.create', ['from' => '/members?gender=' . urlencode('女')]), false);
        $this->actingAs($m)->get('/feedback?from=' . urlencode('/members?gender=女'))->assertSee('value="/members?gender=女"', false);

        $this->actingAs($m)->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148 Line/14.0')
            ->post('/feedback', ['kind' => 'idea', 'body' => '希望可以用地區找人', 'page' => '/members', 'screen' => '390x844'])
            ->assertRedirect('/members')->assertSessionHas('status');
        $f = Feedback::firstOrFail();
        $this->assertSame([$m->id, 'idea', '希望可以用地區找人', '/members', '390x844', 'open'], [$f->user_id, $f->kind, $f->body, $f->page, $f->screen, $f->status]);
        $this->assertSame('手機・LINE 內建瀏覽器・390x844', $f->deviceLabel());
    }

    public function test_only_own_site_paths_and_validation(): void
    {
        $m = $this->member();
        foreach (['https://evil.example/x', '//evil.example/x', '/\\evil.example', 'javascript:alert(1)'] as $bad) {
            $this->actingAs($m)->post('/feedback', ['kind' => 'other', 'body' => '外部網址測試', 'page' => $bad, 'screen' => '<b>'])
                ->assertRedirect(route('home'));
        }
        $this->assertSame(4, Feedback::where('page', '')->where('screen', '')->count());
        $this->actingAs($m)->get('/feedback?from=' . urlencode('https://evil.example'))->assertDontSee('evil.example');

        $this->actingAs($m)->post('/feedback', ['kind' => '', 'body' => '短'])->assertSessionHasErrors(['kind', 'body']);
        $this->actingAs($m)->post('/feedback', ['kind' => 'hack', 'body' => str_repeat('長', 1001)])->assertSessionHasErrors(['kind', 'body']);
    }

    public function test_screenshot_admin_only_and_status_flow(): void
    {
        Storage::fake('avatars');
        $m = $this->member('回報的人');
        $admin = $this->member('管理員', ['is_admin' => true]);
        $this->actingAs($m)->post('/feedback', ['kind' => 'problem', 'body' => '按送禮物沒有反應', 'page' => '/members',
            'screenshot' => UploadedFile::fake()->image('shot.png', 800, 600)])->assertRedirect('/members');
        $f = Feedback::firstOrFail();
        $this->assertNotNull($f->screenshot_path);
        Storage::disk('avatars')->assertExists($f->screenshot_path);

        $this->actingAs($m)->get(route('admin.feedback'))->assertForbidden();
        $this->actingAs($m)->get(route('admin.feedback.screenshot', $f))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.feedback.screenshot', $f))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // 選單有數字；列表看得到會員、頁面、裝置
        $this->actingAs($admin)->get(route('admin.feedback'))->assertOk()->assertSee('回報（1')
            ->assertSee('回報的人')->assertSee('/members')->assertSee('按送禮物沒有反應')->assertSee('看截圖');
        $this->actingAs($admin)->post(route('admin.feedback.status', $f), ['status' => 'doing'])->assertRedirect(route('admin.feedback'));
        $this->assertSame('doing', $f->fresh()->status);
        $this->actingAs($admin)->post(route('admin.feedback.status', $f), ['status' => 'done']);
        $this->assertSame('done', $f->fresh()->status);
        $this->assertSame($admin->id, $f->fresh()->handled_by);
        $this->actingAs($admin)->get(route('admin.feedback'))->assertSee('目前沒有要處理的回報')->assertSee('改回還沒處理');
        $this->actingAs($admin)->post(route('admin.feedback.status', $f), ['status' => 'open']);
        $this->assertNull($f->fresh()->handled_by);
        $this->actingAs($admin)->post(route('admin.feedback.status', $f), ['status' => 'deleted'])->assertSessionHasErrors('status');
        $this->actingAs($m)->post(route('admin.feedback.status', $f), ['status' => 'done'])->assertForbidden();
    }
}
