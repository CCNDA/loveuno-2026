<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\LoveStory;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsLoveStoryTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, string $gender, array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => $gender, 'birth_year' => 1990]);

        return $u;
    }

    public function test_admin_manages_announcements_and_guests_read_them(): void
    {
        $admin = $this->member('管理員', '女', ['is_admin' => true]);
        $u = $this->member('會員', '女');
        $this->actingAs($u)->get('/admin/news')->assertForbidden();

        $this->actingAs($admin)->get('/admin/news/new')->assertOk();
        $this->actingAs($admin)->post('/admin/news', ['title' => '系統維護', 'body' => "週一凌晨維護\n造成不便敬請見諒", 'publish' => 'now'])->assertRedirect('/admin/news');
        $this->actingAs($admin)->post('/admin/news', ['title' => '草稿公告', 'body' => '還沒好', 'publish' => 'draft'])->assertRedirect();
        $this->actingAs($admin)->post('/admin/news', ['title' => '預定公告', 'body' => '明天', 'publish' => 'at'])->assertSessionHasErrors('published_at');
        $this->actingAs($admin)->post('/admin/news', ['title' => '預定公告', 'body' => '明天', 'publish' => 'at', 'published_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertRedirect();
        [$pub, $draft, $later] = Announcement::orderBy('id')->get()->all();

        auth()->logout();
        $this->flushSession(); // 剛剛「存好了」的提示裡有標題
        $this->get('/news')->assertOk()->assertSee('系統維護')->assertDontSee('草稿公告')->assertDontSee('預定公告');
        $this->get("/news/{$pub->id}")->assertOk()->assertSee('週一凌晨維護<br />', false);
        $this->get("/news/{$draft->id}")->assertNotFound();
        $this->get("/news/{$later->id}")->assertNotFound();
        $this->actingAs($admin)->get("/news/{$draft->id}")->assertOk()->assertSee('還沒發佈');

        $this->actingAs($admin)->put("/admin/news/{$draft->id}", ['title' => '草稿改好了', 'body' => '好了', 'publish' => 'now'])->assertRedirect('/admin/news');
        $this->assertTrue($draft->fresh()->isPublished());
        $this->actingAs($admin)->delete("/admin/news/{$pub->id}")->assertRedirect('/admin/news');
        $this->assertNull(Announcement::find($pub->id));
        $this->get('/')->assertSee(route('news.index'), false); // 頁尾
    }

    public function test_submit_review_and_show_love_story(): void
    {
        Storage::fake('avatars');
        $he = $this->member('弟兄', '男');
        $she = $this->member('姊妹', '女');
        $other = $this->member('路人', '女');
        $admin = $this->member('管理員', '男', ['is_admin' => true]);

        $this->get('/love-stories/new')->assertRedirect('/login');
        $this->actingAs($she)->get('/love-stories/new')->assertOk()->assertSee('value="' . $she->id . '"', false);
        $form = ['title' => '神所配合的', 'male_id' => $he->id, 'female_id' => $she->id, 'body' => "我們在愛諾園認識。\n感謝主！"];
        // 性別不對、不是本人
        $this->actingAs($she)->post('/love-stories', ['male_id' => $other->id] + $form)->assertSessionHasErrors('male_id');
        $this->actingAs($other)->post('/love-stories', $form)->assertSessionHasErrors(['male_id' => '請用男方或女方本人的帳號投稿（您的會員編號是 ' . $other->id . '）。']);
        $this->assertSame(0, LoveStory::count());

        $this->actingAs($she)->post('/love-stories', $form + ['photo' => UploadedFile::fake()->image('us.jpg', 1600, 1200)])->assertRedirect();
        $s = LoveStory::sole();
        $this->assertSame('pending', $s->status);
        Storage::disk('avatars')->assertExists($s->photo_path);

        // 審核中：投稿的人、管理員看得到；別人、訪客看不到
        $this->actingAs($she)->get("/love-stories/{$s->id}")->assertOk()->assertSee('審核中');
        $this->actingAs($other)->get("/love-stories/{$s->id}")->assertNotFound();
        $this->actingAs($other)->get("/love-stories/{$s->id}/photo")->assertNotFound();
        $this->actingAs($admin)->get('/admin/love-stories')->assertOk()->assertSee('神所配合的');
        $this->actingAs($she)->post("/admin/love-stories/{$s->id}", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($admin)->post("/admin/love-stories/{$s->id}", ['decision' => 'approve'])->assertRedirect('/admin/love-stories');

        auth()->logout();
        $this->get('/love-stories')->assertOk()->assertSee('神所配合的');
        $this->get("/love-stories/{$s->id}")->assertOk()->assertSee('弟兄')->assertSee('姊妹')->assertSee('感謝主', false);
        $this->get("/love-stories/{$s->id}/photo")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/')->assertSee('愛的見證')->assertSee(route('love-stories.show', $s), false)->assertSee('更多愛的故事');

        // 撤下：照片刪掉、不公開
        $path = $s->photo_path;
        $this->actingAs($admin)->post("/admin/love-stories/{$s->id}", ['decision' => 'reject'])->assertRedirect();
        Storage::disk('avatars')->assertMissing($path);
        auth()->logout();
        $this->get("/love-stories/{$s->id}")->assertNotFound();
        $this->get('/')->assertDontSee('更多愛的故事');
    }

    public function test_welcome_page_follows_design(): void
    {
        $this->get('/')->assertOk()->assertSee('嚴謹認證 安心交友')->assertSee('我們需要您的支持')->assertSee('https://www.ccnda.org/devote/online', false)
            ->assertSee('images/welcome-banner.svg', false);
    }
}
