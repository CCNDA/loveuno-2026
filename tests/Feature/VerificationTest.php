<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use App\Notifications\VerifyJobEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', array $over = [], array $profile = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990] + $profile);

        return $u;
    }

    public function test_job_email_flow(): void
    {
        Notification::fake();
        $me = $this->member('我');
        $admin = $this->member('管理員', ['is_admin' => true]);
        $form = ['company' => '某某科技', 'company_url' => 'https://example.com', 'company_email' => 'me@example.com'];

        $this->actingAs($me)->get('/me/verify')->assertOk()->assertSee('職業 Email 認證')->assertSee('教會週報認證');
        $this->actingAs($me)->post('/me/verify/job', ['company_email' => 'me@gmail.com'] + $form)
            ->assertSessionHasErrorsIn('job', ['company_email' => '請用公司（學校）的 Email，不能用 Gmail、Yahoo 這類公用信箱。']);
        $this->actingAs($me)->post('/me/verify/job', $form)->assertRedirect('/me/verify');

        $url = null;
        Notification::assertSentTo(new AnonymousNotifiable, VerifyJobEmail::class, function ($n, $channels, $notifiable) use (&$url, $me) {
            $url = $n->toMail($notifiable)->actionUrl;

            return $notifiable->routes['mail'] === 'me@example.com';
        });
        $v = Verification::firstOrFail();
        $this->assertSame('email_sent', $v->status);

        // 確認碼錯的 404；打開只是頁面（不算數，連結掃描器會自己開），按按鈕才算；用過就失效
        $this->get(preg_replace('#/[^/]+$#', '/wrong', $url))->assertNotFound();
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('確認，完成 Email 認證');
        $this->assertSame('email_sent', $v->fresh()->status);
        $this->post($url)->assertRedirect('/login');
        $this->assertSame('pending', $v->fresh()->status);
        $this->get($url)->assertNotFound();

        $this->actingAs($admin)->get('/admin/verifications')->assertSee('某某科技')->assertSee('me@example.com');
        $v->refresh();
        $this->actingAs($admin)->post("/admin/verifications/{$v->id}", ['decision' => 'approve', 'version' => $v->updated_at->getTimestamp()])->assertRedirect('/admin/verifications');
        Notification::assertSentTo($me, \App\Notifications\VerificationDecided::class);
        $this->assertSame(10, (int) \Illuminate\Support\Facades\DB::table('point_transactions')->where('user_id', $me->id)->where('type', 'job')->value('amount'));
        $other = $this->member('別人');
        $this->actingAs($other)->get("/members/{$me->id}")->assertSee('alt="已認證"', false)
            ->assertSee('<dt>職業 Email 認證</dt><dd>已認證</dd>', false)->assertSee('<dt>身分文件認證</dt><dd>尚未認證</dd>', false)
            ->assertDontSee('某某科技'); // 公司不顯示於交友檔案
    }

    public function test_id_photo_review_deletes_file(): void
    {
        Storage::fake('avatars');
        $me = $this->member('我');
        $admin = $this->member('管理員', ['is_admin' => true]);
        $this->actingAs($me)->post('/me/verify/id', ['photo' => UploadedFile::fake()->image('id.jpg', 1200, 800)])->assertRedirect('/me/verify');
        $v = Verification::firstOrFail();
        $path = $v->file_path;
        Storage::disk('avatars')->assertExists($path);

        // 只有管理員看得到照片
        $this->actingAs($me)->get("/admin/verifications/{$v->id}/photo")->assertForbidden();
        $this->actingAs($admin)->get("/admin/verifications/{$v->id}/photo")->assertOk();

        $ver = $v->updated_at->getTimestamp();
        $this->actingAs($admin)->post("/admin/verifications/{$v->id}", ['decision' => 'reject', 'version' => $ver])->assertSessionHasErrorsIn('v' . $v->id, 'reason');
        // 版本對不上（會員途中重新上傳）：不套用
        $this->actingAs($admin)->post("/admin/verifications/{$v->id}", ['decision' => 'approve', 'version' => $ver - 100])->assertSessionHas('status', fn ($s) => str_contains($s, '剛剛重新送了'));
        $this->assertSame('pending', $v->fresh()->status);
        $this->actingAs($admin)->post("/admin/verifications/{$v->id}", ['decision' => 'reject', 'reason' => '照片模糊無法辨識', 'version' => $ver])->assertRedirect();
        Storage::disk('avatars')->assertMissing($path);
        $this->assertSame(['rejected', '照片模糊無法辨識', ''], [$v->fresh()->status, $v->fresh()->reject_reason, $v->fresh()->file_path]);
        $this->actingAs($me)->get('/me/verify')->assertSee('沒有通過：照片模糊無法辨識');
        $this->assertArrayNotHasKey('file_path', $v->fresh()->toArray());

        // 可以重新上傳
        $this->actingAs($me)->post('/me/verify/id', ['photo' => UploadedFile::fake()->image('id2.jpg', 1200, 800)])->assertRedirect();
        $this->assertSame('pending', $v->fresh()->status);
        $this->assertSame(1, Verification::count());
    }

    public function test_church_needs_church_filled(): void
    {
        Storage::fake('avatars');
        $me = $this->member('我');
        $this->actingAs($me)->get('/me/verify')->assertSee('請先');
        $this->actingAs($me)->post('/me/verify/church', ['photo' => UploadedFile::fake()->image('c.jpg', 800, 800)])->assertNotFound();

        $me2 = $this->member('有教會', [], ['church' => '某某教會']);
        $this->actingAs($me2)->post('/me/verify/church', ['photo' => UploadedFile::fake()->image('c.jpg', 800, 800)])->assertRedirect();
        $this->assertSame('church', Verification::firstOrFail()->kind);
    }

    public function test_job_link_expires_after_three_days(): void
    {
        Notification::fake();
        $me = $this->member('我');
        $this->actingAs($me)->post('/me/verify/job', ['company' => 'A', 'company_url' => 'https://a.example', 'company_email' => 'me@a.example']);
        $url = null;
        Notification::assertSentTo(new AnonymousNotifiable, VerifyJobEmail::class, function ($n, $c, $notifiable) use (&$url) {
            $url = $n->toMail($notifiable)->actionUrl;

            return true;
        });
        $this->travel(4)->days();
        $this->get($url)->assertNotFound();
    }

    public function test_admin_page_and_nav_only_for_admin(): void
    {
        $me = $this->member('我');
        $this->actingAs($me)->get('/admin/verifications')->assertForbidden();
        $this->actingAs($me)->get('/')->assertDontSee('審核認證');
    }
}
