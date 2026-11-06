<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 小薇 11/6 紀錄片回報：和小布已是朋友，日記內頁 /diaries/82 卻顯示「成為 小布 的朋友以後才能留言」。
// 原因：DiaryController::show 用 load('user:id,name') 只載作者的 id、name，isReachableBy 看 $this->user->is_active 拿到 null，
// 非作者一律判成「看不到」→ 沒有留言框。日記牆是整筆載入，所以只有內頁壞。
class DiaryShowCommentTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        return $u;
    }

    public function test_friend_sees_comment_form_on_diary_page(): void
    {
        $author = $this->member('作者');
        $friend = $this->member('朋友');
        $this->befriend($author, $friend);
        $d = Diary::factory()->for($author)->create(['title' => '一篇日記']);

        $this->actingAs($friend)->get("/diaries/{$d->id}")
            ->assertOk()->assertSee('name="body"', false)->assertDontSee('的朋友以後才能留言');
    }

    public function test_stranger_still_sees_the_hint(): void
    {
        $author = $this->member('作者');
        $stranger = $this->member('路人');
        $d = Diary::factory()->for($author)->create(['title' => '一篇日記']);

        $this->actingAs($stranger)->get("/diaries/{$d->id}")
            ->assertOk()->assertSee('作者 的朋友以後才能留言')->assertDontSee('name="body"', false);
    }
}
