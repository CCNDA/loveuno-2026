<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WriteTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員'): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_edit_profile_but_not_gender_or_birth_year(): void
    {
        $u = $this->member('原名');
        $this->actingAs($u)->get('/me/edit')->assertOk()->assertSee('編輯個人檔案');

        $this->actingAs($u)->put('/me', [
            'name' => '新暱稱', 'live_place' => '台中市', 'job' => '老師', 'church' => '某某教會', 'church_visible' => '1',
            'intro' => "第一行\n第二行", 'hope' => '一起讀經', 'gender' => '男', 'birth_year' => 2000,
        ])->assertRedirect('/me');

        $p = $u->profile->fresh();
        $this->assertSame('新暱稱', $u->fresh()->name);
        $this->assertSame(['台中市', '老師', true, '女', 1990], [$p->live_place, $p->job, $p->church_visible, $p->gender, $p->birth_year]);
    }

    public function test_profile_validation(): void
    {
        $u = $this->member();
        $this->actingAs($u)->put('/me', ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($u)->put('/me', ['name' => 'x', 'intro' => str_repeat('長', 2001)])->assertSessionHasErrors('intro');
    }

    public function test_write_edit_delete_own_diary(): void
    {
        $u = $this->member();
        $this->actingAs($u)->get('/diaries/create')->assertOk();
        $this->actingAs($u)->post('/diaries', ['title' => '第一篇', 'body' => '內容', 'visibility' => 'private'])->assertRedirect();

        $d = Diary::where('title', '第一篇')->firstOrFail();
        $this->assertSame([$u->id, 'private', now()->toDateString()], [$d->user_id, $d->visibility, $d->published_on->toDateString()]);

        $this->actingAs($u)->put("/diaries/{$d->id}", ['title' => '改過', 'body' => '新內容', 'visibility' => 'public'])->assertRedirect("/diaries/{$d->id}");
        $this->assertSame(['改過', 'public'], [$d->fresh()->title, $d->fresh()->visibility]);

        $this->actingAs($u)->delete("/diaries/{$d->id}")->assertRedirect('/diaries');
        $this->assertNull(Diary::find($d->id));
    }

    public function test_cannot_touch_someone_elses_diary(): void
    {
        $owner = $this->member('作者');
        $other = $this->member('別人');
        $d = Diary::factory()->for($owner)->create(['title' => '別人的']);

        $this->actingAs($other)->get("/diaries/{$d->id}/edit")->assertNotFound();
        $this->actingAs($other)->put("/diaries/{$d->id}", ['title' => '竄改', 'body' => 'x', 'visibility' => 'public'])->assertNotFound();
        $this->actingAs($other)->delete("/diaries/{$d->id}")->assertNotFound();
        $this->assertSame('別人的', $d->fresh()->title);
    }

    public function test_diary_validation_and_user_id_cannot_be_forged(): void
    {
        $u = $this->member();
        $victim = $this->member('受害者');
        $this->actingAs($u)->post('/diaries', ['title' => '', 'body' => 'x', 'visibility' => 'public'])->assertSessionHasErrors('title');
        $this->actingAs($u)->post('/diaries', ['title' => 't', 'body' => 'x', 'visibility' => 'friends'])->assertSessionHasErrors('visibility');
        $this->actingAs($u)->post('/diaries', ['title' => '偽造', 'body' => 'x', 'visibility' => 'public', 'user_id' => $victim->id]);
        $this->assertSame($u->id, Diary::where('title', '偽造')->value('user_id'));
    }

    public function test_limits_unchecking_and_profile_created_when_missing(): void
    {
        $u = $this->member();
        $this->actingAs($u)->put('/me', ['name' => str_repeat('名', 21)])->assertSessionHasErrors('name');
        $this->actingAs($u)->put('/me', ['name' => 'x', 'hope' => str_repeat('長', 1001)])->assertSessionHasErrors('hope');
        $this->actingAs($u)->post('/diaries', ['title' => str_repeat('題', 151), 'body' => 'x', 'visibility' => 'public'])->assertSessionHasErrors('title');
        $this->actingAs($u)->post('/diaries', ['title' => 't', 'body' => str_repeat('字', 10001), 'visibility' => 'public'])->assertSessionHasErrors('body');

        // 先勾、再取消：存成 false
        $this->actingAs($u)->put('/me', ['name' => 'x', 'church_visible' => '1']);
        $this->assertTrue($u->profile->fresh()->church_visible);
        $this->actingAs($u)->put('/me', ['name' => 'x']);
        $this->assertFalse($u->profile->fresh()->church_visible);

        // 沒有 profile 的帳號，存檔時會建一筆
        $bare = User::factory()->create();
        $this->actingAs($bare)->put('/me', ['name' => '新人', 'live_place' => '高雄市'])->assertRedirect("/members/{$bare->id}");
        $this->assertSame('高雄市', $bare->fresh()->profile->live_place);
    }

    public function test_edit_link_only_for_author_and_put_cannot_change_owner(): void
    {
        $owner = $this->member('作者');
        $other = $this->member('別人');
        $d = $owner->diaries()->create(['title' => '原本', 'body' => 'x', 'visibility' => 'public', 'published_on' => now()->toDateString()]);

        $this->actingAs($owner)->get("/diaries/{$d->id}")->assertSee('修改這篇');
        $this->actingAs($other)->get("/diaries/{$d->id}")->assertOk()->assertDontSee('修改這篇');

        $this->actingAs($owner)->put("/diaries/{$d->id}", ['title' => '改', 'body' => 'x', 'visibility' => 'public', 'user_id' => $other->id]);
        $this->assertSame($owner->id, $d->fresh()->user_id);
    }

    public function test_delete_asks_first(): void
    {
        $u = $this->member();
        $d = $u->diaries()->create(['title' => '要刪的', 'body' => 'x', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $this->actingAs($u)->get("/diaries/{$d->id}/edit")->assertDontSee('確定刪掉')->assertSee('刪掉這篇日記');
        $this->actingAs($u)->get("/diaries/{$d->id}/delete")->assertOk()->assertSee('確定要刪掉這篇日記嗎')->assertSee('要刪的');
        $this->assertNotNull($d->fresh());
        $other = $this->member('別人');
        $this->actingAs($other)->get("/diaries/{$d->id}/delete")->assertNotFound();
    }

    public function test_set_profile_command(): void
    {
        $u = $this->member();
        $this->artisan('user:set-profile', ['email' => $u->email, '--gender' => '男', '--birth-year' => '1985'])->assertSuccessful();
        $this->assertSame(['男', 1985], [$u->profile->fresh()->gender, $u->profile->fresh()->birth_year]);
        $this->artisan('user:set-profile', ['email' => $u->email, '--gender' => 'x'])->assertFailed();
        $this->artisan('user:set-profile', ['email' => $u->email, '--birth-year' => (string) ((int) now()->format('Y') - 10)])->assertFailed();
        $this->artisan('user:set-profile', ['email' => $u->email])->assertFailed();
    }

    public function test_guest_cannot_write(): void
    {
        $this->get('/diaries/create')->assertRedirect('/login');
        $this->post('/diaries', ['title' => 't', 'body' => 'b', 'visibility' => 'public'])->assertRedirect('/login');
        $this->get('/me/edit')->assertRedirect('/login');
        $this->put('/me', ['name' => 'x'])->assertRedirect('/login');
        $d = Diary::factory()->create();
        $this->put("/diaries/{$d->id}", ['title' => 't', 'body' => 'b', 'visibility' => 'public'])->assertRedirect('/login');
        $this->delete("/diaries/{$d->id}")->assertRedirect('/login');
        $this->assertNotNull($d->fresh());
    }
}
