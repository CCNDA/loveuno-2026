<?php

namespace Tests\Feature;

use App\Models\DiaryComment;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 頁首通知鈴的浮動框（熊叔叔 2026-11-05 TG「右上角通知可以點開浮動的資訊」；設計稿 Info_Description 819:11123）
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): User
    {
        $u = User::factory()->create(["name" => $name]);
        Profile::create(["user_id" => $u->id, "gender" => "女", "birth_year" => 1990, "live_place" => "台北市"]);

        return $u;
    }

    public function test_empty_then_comment_on_my_diary_shows_up(): void
    {
        $me = $this->member("我");
        $other = $this->member("留言的人");
        $this->actingAs($me)->get("/")->assertSee("<details class=\"notif\">", false)->assertSee("最近兩週沒有新通知");

        $d = $me->diaries()->create(["title" => "我的日記", "body" => "內容", "visibility" => "public", "published_on" => now()->toDateString()]);
        $c = new DiaryComment(["body" => "加油"]);
        $c->user_id = $other->id;
        $c->diary_id = $d->id;
        $c->save();
        $mine = new DiaryComment(["body" => "自己留的"]);
        $mine->user_id = $me->id;
        $mine->diary_id = $d->id;
        $mine->save();

        $html = $this->actingAs($me)->get("/")->getContent();
        $this->assertStringContainsString("留言的人 回應了你的日記〈我的日記〉。", $html);
        $this->assertStringContainsString("/diaries/" . $d->id . "#comments-title", $html);
        $this->assertStringNotContainsString("我 回應了你的日記", $html); // 自己留的不算
        $this->assertStringContainsString("剛剛", $html);
    }

    public function test_guest_has_no_bell(): void
    {
        $this->get("/")->assertDontSee("class=\"notif\"", false);
    }
}
