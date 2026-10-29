<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Support\LoveHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoveHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function answers(array $over = []): array
    {
        $a = [];
        foreach (LoveHistory::QUESTIONS as $k => [$label, $options]) {
            $a[$k] = $options[1];
        }

        return array_merge($a, $over);
    }

    public function test_questionnaire_saves_awards_points_and_shows(): void
    {
        $u = User::factory()->create(['name' => '我']);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);
        $this->actingAs($u)->get('/me/love')->assertOk()->assertSee('目前的感情狀態')->assertSee('對於與非基督徒交往的感覺');

        $this->actingAs($u)->put('/me/love', $this->answers(['cvcurrent' => '保留', 'cvnote' => '希望一起服事']))
            ->assertRedirect("/members/{$u->id}?tab=intro");
        $this->assertSame('希望一起服事', $u->profile->fresh()->love_history['cvnote']);
        $this->assertSame(10, (int) DB::table('point_transactions')->where('user_id', $u->id)->where('type', 'intro')->value('amount'));

        $other = User::factory()->create();
        Profile::create(['user_id' => $other->id, 'gender' => '男', 'birth_year' => 1990]);
        $this->actingAs($other)->get("/members/{$u->id}?tab=intro")->assertSee('戀愛履歷')->assertSee('只談過一次戀愛')
            ->assertSee('希望一起服事')->assertDontSee('<dt>目前的感情狀態</dt>', false); // 選「保留」的不列
    }

    public function test_all_choices_required_and_whitelisted(): void
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);
        $this->actingAs($u)->put('/me/love', $this->answers(['cvpast' => '']))->assertSessionHasErrors('cvpast');
        $this->actingAs($u)->put('/me/love', $this->answers(['cvhopeway' => '亂填']))->assertSessionHasErrors('cvhopeway');
        $this->actingAs($u)->put('/me/love', $this->answers(['cvnote' => str_repeat('字', 501)]))->assertSessionHasErrors('cvnote');
        $this->assertNull($u->profile->fresh()->love_history);
        $this->assertSame(0, DB::table('point_transactions')->where('type', 'intro')->count());
    }
}
