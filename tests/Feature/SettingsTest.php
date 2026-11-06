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
            ->assertSee('帳號管理')->assertSee('會員編號')->assertSee('帳號開關')->assertSee('網路檢測')->assertSee('註銷帳號');
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
        $this->befriend($a, $b);
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '你好']);
        $this->actingAs($a)->post("/members/{$b->id}/gift", ['step' => 'send', 'item' => 1]);
        Notification::assertSentToTimes($b, NewActivity::class, 2);
        Notification::assertSentTo($b, NewActivity::class, fn ($n) => ! str_contains(json_encode($n->toMail($b)->toArray(), JSON_UNESCAPED_UNICODE), '甲')); // 信裡沒有暱稱

        $b->forceFill(['notify_email' => false])->save();
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '再一次']);
        Notification::assertSentToTimes($b, NewActivity::class, 2);
    }

    public function test_paused_member_photos_and_comments_hidden_and_cannot_send(): void
    {
        $me = $this->member('暫停的');
        $me->profile->forceFill(['photo_path' => 'p.jpg', 'photo_status' => 'approved'])->save();
        $other = $this->member('別人');
        $d = $other->diaries()->create(['title' => '別人的日記', 'body' => 'b', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $c = new \App\Models\DiaryComment(['body' => '暫停者的留言']);
        $c->forceFill(['diary_id' => $d->id, 'user_id' => $me->id])->save();
        $me->forceFill(['is_active' => false])->save();

        $this->actingAs($other)->get("/photos/{$me->id}")->assertNotFound();
        $this->actingAs($other)->get("/diaries/{$d->id}")->assertDontSee('暫停者的留言');
        $this->befriend($me, $other);
        $this->actingAs($me)->post("/notes/{$other->id}", ['body' => 'x'])->assertNotFound(); // 暫停的人也不能主動傳
    }

    public function test_huninn_font_only_loaded_when_chosen(): void
    {
        $u = $this->member();
        $this->actingAs($u)->get('/center/account')->assertDontSee('huninn.css')->assertSee('粉圓');
        $this->actingAs($u)->put('/center/settings', $this->settings(['font_family' => 'huninn']))->assertRedirect();
        $this->actingAs($u)->get('/center/account')->assertSee('huninn.css')->assertSee('ff-huninn');
        $this->actingAs($u)->put('/center/settings', $this->settings(['font_family' => 'comic']))->assertSessionHasErrorsIn('settings', 'font_family');
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
        $reason = '找到另一半了，謝謝愛諾園的幫助！';
        $this->actingAs($u)->delete('/center/account', ['current_password' => 'wrong', 'confirm' => '1', 'reason' => $reason])->assertSessionHasErrorsIn('delete', 'current_password');
        $this->actingAs($u)->delete('/center/account', ['current_password' => 'password', 'reason' => $reason])->assertSessionHasErrorsIn('delete', 'confirm');
        $this->actingAs($u)->delete('/center/account', ['current_password' => 'password', 'confirm' => '1', 'reason' => '太短'])->assertSessionHasErrorsIn('delete', 'reason');

        // 別人收到的紙條要留著（證據）
        $victim = $this->member('收紙條的人');
        $this->befriend($u, $victim);
        $this->actingAs($u)->post("/notes/{$victim->id}", ['body' => '要留下來的紙條']);
        \Illuminate\Support\Facades\Notification::fake();
        $this->actingAs($u)->delete('/center/account', ['current_password' => 'password', 'confirm' => '1', 'reason' => $reason])->assertRedirect('/');
        $this->assertGuest();
        $gone = User::find($u->id);
        $this->assertSame(['已註銷會員', false], [$gone->name, $gone->is_active]);
        $this->assertNotNull($gone->deleted_at);
        $this->assertStringEndsWith('@deleted.invalid', $gone->email);
        $this->assertNull($gone->profile()->first());
        Storage::disk('avatars')->assertMissing($path);
        $this->assertSame(1, \App\Models\Note::where('sender_id', $u->id)->count());
        $this->actingAs($victim)->get("/notes/{$u->id}")->assertSee('要留下來的紙條')->assertSee('已註銷會員');
        $this->actingAs($victim)->get("/members/{$u->id}")->assertNotFound();
        \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\AccountDeleted::class);
    }
}
