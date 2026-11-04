<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\LoveStory;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 愛的見證通過時，兩位改成已婚（上線前檢測表 D-16；預設勾選，可以取消）
class LoveStoryMarriedTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, string $gender): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => $gender, 'birth_year' => 1990, 'marital' => '未婚']);

        return $u;
    }

    private function story(User $he, User $she): LoveStory
    {
        return LoveStory::forceCreate(['submitter_id' => $she->id, 'male_id' => $he->id, 'female_id' => $she->id,
            'title' => '我們結婚了', 'body' => '在愛諾園認識、交往、結婚的見證。', 'status' => 'pending']);
    }

    public function test_approve_with_checkbox_sets_both_married_and_logs(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $he = $this->member('弟兄甲', '男');
        $she = $this->member('姊妹乙', '女');
        $s = $this->story($he, $she);

        $this->actingAs($admin)->get(route('admin.love-stories'))->assertSee('同時把弟兄甲、姊妹乙的婚姻狀況改成「已婚」')
            ->assertSee('name="set_married" type="checkbox" value="1" checked', false);
        $this->actingAs($admin)->post(route('admin.love-stories.decide', $s), ['decision' => 'approve', 'set_married' => '1'])
            ->assertRedirect(route('admin.love-stories'))->assertSessionHas('status', fn ($m) => str_contains($m, '弟兄甲、姊妹乙 的婚姻狀況改成「已婚」'));
        $this->assertSame('已婚', $he->profile->fresh()->marital);
        $this->assertSame('已婚', $she->profile->fresh()->marital);
        $this->assertSame(2, AdminAction::where('action', 'marital')->count());
        $this->assertStringContainsString('愛的見證 #' . $s->id, AdminAction::first()->detail['note']);

        // 已婚就不會出現在認識朋友（先清掉上面通過時的提示訊息，不然它會顯示在下一頁）
        $this->flushSession();
        $other = $this->member('別人', '女');
        $this->actingAs($other)->get('/members')->assertDontSee('弟兄甲')->assertDontSee('姊妹乙');
    }

    public function test_approve_without_checkbox_leaves_marital(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $he = $this->member('弟兄丙', '男');
        $she = $this->member('姊妹丁', '女');
        $s = $this->story($he, $she);
        $this->actingAs($admin)->post(route('admin.love-stories.decide', $s), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $s->fresh()->status);
        $this->assertSame('未婚', $he->profile->fresh()->marital);
        $this->assertSame(0, AdminAction::count());
    }
}
