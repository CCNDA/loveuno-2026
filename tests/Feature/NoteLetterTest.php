<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 熊叔叔 11/6 決定 #8：傳紙條改「情書」樣式——每封有標題、清單一封一封、可勾選刪除（只刪自己這邊）
class NoteLetterTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        return $u;
    }

    private function note(User $from, User $to, string $body, ?string $title = null): Note
    {
        $n = new Note(['body' => $body, 'title' => $title]);
        $n->sender_id = $from->id;
        $n->recipient_id = $to->id;
        $n->save();

        return $n;
    }

    public function test_send_with_title_and_list_shows_each_letter(): void
    {
        [$a, $b] = [$this->member('甲'), $this->member('乙')];
        $this->befriend($a, $b);
        $this->actingAs($a)->post(route('notes.store', $b), ['title' => '週末聚會', 'body' => '要一起去嗎？'])->assertRedirect();
        $this->assertSame('週末聚會', Note::sole()->title);
        $this->actingAs($a)->post(route('notes.store', $b), ['title' => str_repeat('長', 21), 'body' => '太長'])->assertSessionHasErrors('title');

        $this->note($b, $a, "舊紙條沒有標題，內文很長很長很長很長很長很長很長很長很長");
        $html = $this->actingAs($a)->get('/notes')->assertOk()->getContent();
        $this->assertStringContainsString('您有 <span class="threads-count">1</span> 封新情書未讀', $html);
        $this->assertStringContainsString('週末聚會', $html);
        $this->assertStringContainsString('舊紙條沒有標題，內文很長很長很長很長很長…', $html); // 沒標題：內文前 20 字
        $this->assertSame(2, substr_count($html, 'name="ids[]"'));
    }

    public function test_delete_only_hides_my_side(): void
    {
        [$a, $b, $c] = [$this->member('甲'), $this->member('乙'), $this->member('丙')];
        $n1 = $this->note($b, $a, '乙寫給甲', '第一封');
        $n2 = $this->note($a, $b, '甲回乙', '回信');
        $other = $this->note($b, $c, '乙寫給丙', '別人的');

        $this->actingAs($a)->post(route('notes.destroy'), ['ids' => [$n1->id, $n2->id, $other->id]])->assertRedirect('/notes')
            ->assertSessionHas('status', '刪掉了 2 封信。');
        $this->assertNotNull($n1->fresh()->recipient_deleted_at);
        $this->assertNotNull($n2->fresh()->sender_deleted_at);
        $this->assertNull($other->fresh()->recipient_deleted_at); // 不是我的不能刪
        $this->assertNull($other->fresh()->sender_deleted_at);

        $this->actingAs($a)->get('/notes')->assertDontSee('第一封')->assertDontSee('回信</span>', false);
        $this->actingAs($a)->get(route('notes.show', $b))->assertDontSee('乙寫給甲');
        $this->actingAs($b)->get(route('notes.show', $a))->assertSee('乙寫給甲')->assertSee('甲回乙'); // 對方還在
        $this->assertSame(0, Note::unreadCountFor($a));

        $this->actingAs($a)->post(route('notes.destroy'), [])->assertSessionHas('status', '請先勾選要刪除的信。');
        $this->post(route('notes.destroy'), ['ids' => [$n1->id]]); // 還是登入中的甲，重刪不會出錯
        $this->assertNotNull($n1->fresh()->recipient_deleted_at);
    }
}
