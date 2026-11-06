<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 熊叔叔 11/6 TG「傳紙條需要相互完成送禮物才能開啟」：交友三步驟第二步，互送禮物成為普通好友以後才能寄紙條
class NoteGiftGateTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        return $u;
    }

    public function test_must_exchange_gifts_before_sending_notes(): void
    {
        [$a, $b] = [$this->member('甲'), $this->member('乙')];

        // 還不是普通好友：頁面說明要先互送禮物、沒有寄紙條的表單；直接送也不會寄出
        $this->actingAs($a)->get("/notes/{$b->id}")->assertOk()->assertSee('要先互送禮物成為「普通好友」')
            ->assertSee(route('gifts.create', $b), false)->assertDontSee('name="body"', false);
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '還不是好友'])->assertRedirect("/notes/{$b->id}")
            ->assertSessionHas('status', '要先互送禮物成為「普通好友」，才能傳紙條。');
        $this->assertSame(0, Note::count());

        // 以前（規則改之前）就有往來的紙條：還看得到，但不能再寄
        $old = new Note(['body' => '規則改之前的紙條']);
        $old->sender_id = $b->id;
        $old->recipient_id = $a->id;
        $old->save();
        $this->actingAs($a)->get("/notes/{$b->id}")->assertSee('規則改之前的紙條')->assertDontSee('name="body"', false);

        // 互送禮物以後（普通好友）就可以寄
        $this->befriend($a, $b);
        $this->actingAs($a)->get("/notes/{$b->id}")->assertSee('name="body"', false)->assertDontSee('要先互送禮物成為「普通好友」');
        $this->actingAs($a)->post("/notes/{$b->id}", ['body' => '現在可以了'])->assertSessionHas('status', '紙條送出了。');
        $this->assertSame(2, Note::count());
    }
}
