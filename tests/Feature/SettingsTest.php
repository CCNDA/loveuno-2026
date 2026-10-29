<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Notifications\NewActivity;
use App\Support\Points;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    private function settings(array $over = []): array
    {
        return array_merge(['notify_email' => '1', 'is_active' => '1', 'font_size' => 'medium', 'script' => 'hant'], $over);
    }

    public function test_settings_page_and_save(): void
    {
        $u = $this->member('我');
        $this->actingAs($u)->get('/center/account')->assertOk()
            ->assertSee('帳號設定')->assertSee('會員編號')->assertSee('啟用帳號')->assertSee('網路檢測')->assertSee('註銷帳號');
        $this->actingAs($u)->put('/center/settings', $this->settings(['font_size' => 'large', 'script' => 'hans', 'notify_email' => null]))->assertRedirect();
        $u->refresh();
        $this->assertSame(['large', 'hans', false], [$u->font_size, $u->script, $u->notify_email]);
        $this->actingAs($u)->get('/center/account')->assertSee('class="fs-large"', false);
        $this->actingAs($u)->put('/center/settings', $this->settings(['font_size' => 'huge']))->assertSessionHasErrorsIn('settings', 'font_size');
    }

    public function test_paused_account_is_hidden(): void
    {
        $me = $this->member('暫停的');
        $me->diaries()->create(['title' => '暫停者的日記', 'body' => 'b', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $other = $this->member('別人');
        $this->actingAs($me)->put('/center/settings', $this->settings(['is_active' => null]))->assertSessionHas('status', fn ($s) => str_contains($s, '暫停'));

        $this->actingAs($other)->get("/members/{$me->id}")->assertNotFound();
        $this->actingAs($other)->get('/members')->assertDontSee(route('members.show', $me), false);
        $this->actingAs($other)->get('/')->assertDontSee('暫停者的日記');
        $this->actingAs($other)->get("/notes/{$me->id}")->assertNotFound();
        $this->actingAs($me)->get("/members/{$me->id}")->assertOk(); // 自己看得到
        $this->actingAs($me)->get('/')->assertSee('暫停者的日記');
    }

    public function test_email_notice_on_note_and_gift(): void
    {
        Notification::fake();
        $a = $this->member('甲');
        $b = $this->member('乙');
        Points::award($a, 'test', 'x', 10, '測試');
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '你好']);
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['item' => 1]);
        Notification::assertSentToTimes($b, NewActivity::class, 2);

        $b->forceFill(['notify_email' => false])->save();
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '再一次']);
        Notification::assertSentToTimes($b, NewActivity::class, 2);
    }

    public function test_network_page(): void
    {
        $u = $this->member();
        $this->actingAs($u)->get('/center/network')->assertOk()->assertSee('連線正常');
    }

    public function test_delete_account_removes_user_and_files(): void
    {
        Storage::fake('avatars');
        $u = $this->member('要走的');
        $this->actingAs($u)->post('/me/album', ['photos' => [UploadedFile::fake()->image('a.jpg', 400, 400)]]);
        $path = \App\Models\AlbumPhoto::firstOrFail()->path;

        $this->actingAs($u)->get('/center/delete')->assertOk()->assertSee('不能恢復');
        $this->actingAs($u)->delete('/center/account', ['current_password' => 'wrong', 'confirm' => '1'])->assertSessionHasErrorsIn('delete', 'current_password');
        $this->actingAs($u)->delete('/center/account', ['current_password' => 'password'])->assertSessionHasErrorsIn('delete', 'confirm');
        $this->actingAs($u)->delete('/center/account', ['current_password' => 'password', 'confirm' => '1'])->assertRedirect('/');
        $this->assertNull(User::find($u->id));
        Storage::disk('avatars')->assertMissing($path);
        $this->assertGuest();
    }
}
