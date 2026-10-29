<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\DiaryComment;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrayerTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    private function diary(User $author, array $over = []): Diary
    {
        return $author->diaries()->create($over + [
            'title' => '相信神的帶領', 'body' => '受洗後面對一連串的難題', 'visibility' => 'public', 'published_on' => now()->toDateString(),
        ]);
    }

    private function block(User $a, User $b): void
    {
        DB::table('blocks')->insert(['blocker_id' => $a->id, 'blocked_id' => $b->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_mark_diary_as_prayer_request_shows_tag(): void
    {
        $a = $this->member('作者');
        $this->actingAs($a)->post('/diaries', ['title' => '請為我禱告', 'body' => '下週面試', 'visibility' => 'public', 'is_prayer' => '1'])->assertRedirect();
        $d = Diary::firstOrFail();
        $this->assertTrue($d->is_prayer);

        $viewer = $this->member('讀者');
        $this->actingAs($viewer)->get('/')->assertSee('<span class="tag tag-prayer">代禱</span>', false);
        $this->actingAs($viewer)->get('/diaries')->assertSee('代禱');

        // 改的時候取消勾選
        $this->actingAs($a)->put("/diaries/{$d->id}", ['title' => '請為我禱告', 'body' => '下週面試', 'visibility' => 'public'])->assertRedirect();
        $this->assertFalse($d->fresh()->is_prayer);
    }

    public function test_pray_toggles_and_counts_once_per_person(): void
    {
        $d = $this->diary($this->member('作者'));
        $v = $this->member('讀者');

        $this->actingAs($v)->post("/diaries/{$d->id}/pray", ['from' => 'wall'])->assertRedirect('/#d' . $d->id)->assertSessionHas('status', '謝謝您為這篇日記禱告。');
        $this->actingAs($v)->get('/')->assertSee('aria-pressed="true"', false)->assertSee('1<span class="visually-hidden"> 人禱告</span>', false);

        $this->actingAs($v)->post("/diaries/{$d->id}/pray", ['from' => 'list', 'page' => 2])->assertRedirect('/diaries?page=2#d' . $d->id);
        $this->assertSame(0, DB::table('diary_prayers')->count());

        // from 只認三種，其他一律回日記內頁（不能被帶去外站）
        $this->actingAs($v)->post("/diaries/{$d->id}/pray", ['from' => 'https://evil.example/'])->assertRedirect("/diaries/{$d->id}#d{$d->id}");
        $this->assertSame(1, DB::table('diary_prayers')->count());
    }

    public function test_comment_then_list_and_delete_rules(): void
    {
        $author = $this->member('作者');
        $d = $this->diary($author);
        $v = $this->member('讀者');
        $other = $this->member('路人');

        $r = $this->actingAs($v)->post("/diaries/{$d->id}/comments", ['body' => "為你禱告\n<b>加油</b>"]);
        $c = DiaryComment::firstOrFail();
        $r->assertRedirect("/diaries/{$d->id}#comment-{$c->id}");
        $this->assertSame($v->id, $c->user_id);

        $html = $this->actingAs($other)->get("/diaries/{$d->id}")->assertOk()->getContent();
        $this->assertStringContainsString('為你禱告<br />', $html);
        $this->assertStringContainsString('&lt;b&gt;加油&lt;/b&gt;', $html); // 有 escape
        $this->assertStringContainsString('留言（1）', $html);
        $this->assertStringNotContainsString('刪掉這則留言', $html); // 路人不能刪

        $this->actingAs($other)->delete("/diaries/{$d->id}/comments/{$c->id}")->assertNotFound();
        $this->actingAs($author)->get("/diaries/{$d->id}")->assertSee('刪掉這則留言');
        $this->actingAs($author)->delete("/diaries/{$d->id}/comments/{$c->id}")->assertRedirect();
        $this->assertSame(0, DiaryComment::count());

        // 驗證：空白、太長
        $this->actingAs($v)->post("/diaries/{$d->id}/comments", ['body' => ''])->assertSessionHasErrors('body');
        $this->actingAs($v)->post("/diaries/{$d->id}/comments", ['body' => str_repeat('字', 501)])->assertSessionHasErrors('body');
    }

    public function test_comment_on_other_diary_url_is_rejected(): void
    {
        $a = $this->member('作者');
        $d1 = $this->diary($a);
        $d2 = $this->diary($a, ['title' => '另一篇']);
        $v = $this->member('讀者');
        $c = new DiaryComment(['body' => 'x']);
        $c->forceFill(['diary_id' => $d1->id, 'user_id' => $v->id])->save();

        $this->actingAs($v)->delete("/diaries/{$d2->id}/comments/{$c->id}")->assertNotFound();
        $this->assertSame(1, DiaryComment::count());
    }

    public function test_private_and_blocked_diaries_cannot_be_prayed_or_commented(): void
    {
        $author = $this->member('作者');
        $private = $this->diary($author, ['visibility' => 'private']);
        $public = $this->diary($author, ['title' => '公開的']);
        $v = $this->member('讀者');

        $this->actingAs($v)->post("/diaries/{$private->id}/pray")->assertNotFound();
        $this->actingAs($v)->post("/diaries/{$private->id}/comments", ['body' => 'hi'])->assertNotFound();

        $this->block($author, $v);
        $this->actingAs($v)->post("/diaries/{$public->id}/pray")->assertNotFound();
        $this->actingAs($v)->post("/diaries/{$public->id}/comments", ['body' => 'hi'])->assertNotFound();
        $this->assertSame(0, DB::table('diary_prayers')->count() + DiaryComment::count());
    }

    public function test_comments_from_blocked_people_are_hidden(): void
    {
        $author = $this->member('作者');
        $d = $this->diary($author);
        $bad = $this->member('被封鎖的人');
        $v = $this->member('讀者');
        $c = new DiaryComment(['body' => '不想看到的留言']);
        $c->forceFill(['diary_id' => $d->id, 'user_id' => $bad->id])->save();

        $this->actingAs($v)->get("/diaries/{$d->id}")->assertSee('不想看到的留言');
        $this->block($v, $bad);
        $this->actingAs($v)->get("/diaries/{$d->id}")->assertDontSee('不想看到的留言');
    }

    public function test_diary_image_needs_review_before_others_see_it(): void
    {
        Storage::fake('avatars');
        $a = $this->member('作者');
        $admin = $this->member('管理員', ['is_admin' => true]);
        $v = $this->member('讀者');

        $this->actingAs($a)->post('/diaries', [
            'title' => '有照片', 'body' => '內容', 'visibility' => 'public',
            'image' => UploadedFile::fake()->image('p.png', 2400, 1200),
        ])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, '審核'));
        $d = Diary::firstOrFail();
        $this->assertSame('pending', $d->image_status);
        [$w, $h] = getimagesizefromstring(Storage::disk('avatars')->get($d->image_path));
        $this->assertSame([1200, 600], [$w, $h]); // 只縮不裁

        $this->actingAs($a)->get("/diaries/{$d->id}/image")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($v)->get("/diaries/{$d->id}/image")->assertNotFound();
        $this->actingAs($v)->get('/')->assertDontSee("/diaries/{$d->id}/image", false);

        $this->actingAs($admin)->get('/admin/photos')->assertSee('日記照片')->assertSee("/diaries/{$d->id}/image", false);
        $this->actingAs($admin)->post("/admin/diary-images/{$d->id}", ['decision' => 'approve', 'photo' => $d->image_path])->assertRedirect('/admin/photos');
        $this->actingAs($v)->get("/diaries/{$d->id}/image")->assertOk();
        $this->actingAs($v)->get('/')->assertSee("/diaries/{$d->id}/image", false);

        // 拿掉照片：檔案刪掉
        $path = $d->fresh()->image_path;
        $this->actingAs($a)->put("/diaries/{$d->id}", ['title' => '有照片', 'body' => '內容', 'visibility' => 'public', 'remove_image' => '1'])->assertRedirect();
        Storage::disk('avatars')->assertMissing($path);
        $this->assertNull($d->fresh()->image_path);
    }

    public function test_rejected_diary_image_is_deleted_and_stale_review_refused(): void
    {
        Storage::fake('avatars');
        $a = $this->member('作者');
        $admin = $this->member('管理員', ['is_admin' => true]);
        $this->actingAs($a)->post('/diaries', ['title' => 't', 'body' => 'b', 'visibility' => 'public', 'image' => UploadedFile::fake()->image('p.jpg', 800, 800)]);
        $d = Diary::firstOrFail();

        $this->actingAs($admin)->post("/admin/diary-images/{$d->id}", ['decision' => 'approve', 'photo' => 'old-one.jpg'])->assertSessionHas('status', fn ($s) => str_contains($s, '剛剛換了'));
        $this->assertSame('pending', $d->fresh()->image_status);

        $path = $d->image_path;
        $this->actingAs($admin)->post("/admin/diary-images/{$d->id}", ['decision' => 'reject', 'photo' => $path]);
        Storage::disk('avatars')->assertMissing($path);
        $this->assertSame('rejected', $d->fresh()->image_status);
        $this->actingAs($a)->post("/admin/diary-images/{$d->id}", ['decision' => 'approve', 'photo' => 'x'])->assertForbidden();
    }

    public function test_deleting_diary_removes_image_prayers_and_comments(): void
    {
        Storage::fake('avatars');
        $a = $this->member('作者');
        $this->actingAs($a)->post('/diaries', ['title' => 't', 'body' => 'b', 'visibility' => 'public', 'image' => UploadedFile::fake()->image('p.jpg', 800, 800)]);
        $d = Diary::firstOrFail();
        $v = $this->member('讀者');
        $this->actingAs($v)->post("/diaries/{$d->id}/pray");
        $this->actingAs($v)->post("/diaries/{$d->id}/comments", ['body' => 'hi']);

        $path = $d->image_path;
        $this->actingAs($a)->delete("/diaries/{$d->id}")->assertRedirect('/diaries');
        Storage::disk('avatars')->assertMissing($path);
        $this->assertSame(0, DB::table('diary_prayers')->count() + DiaryComment::count());
    }

    public function test_bad_image_rejected_without_saving_diary(): void
    {
        Storage::fake('avatars');
        $a = $this->member('作者');
        $this->actingAs($a)->post('/diaries', ['title' => 't', 'body' => 'b', 'visibility' => 'public', 'image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('image');
        $this->assertSame(0, Diary::count());
    }

    public function test_relative_time_in_chinese(): void
    {
        $c = new DiaryComment;
        foreach ([[30, '剛剛'], [600, '10 分鐘前'], [3600 * 20, '20 小時前'], [86400, '1 天前']] as [$sec, $want]) {
            $c->created_at = now()->subSeconds($sec);
            $this->assertSame($want, $c->ago());
        }
    }
}
