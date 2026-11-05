<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 即時存檔（熊叔叔 2026-11-05「沒有存檔是要做成即時存檔，這個要走 rest api 即時儲存」）
class AutosaveTest extends TestCase
{
    use RefreshDatabase;

    private function me(): User
    {
        $u = User::factory()->create(['name' => '原本']);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'faith' => '基督徒', 'job' => '老師']);

        return $u;
    }

    public function test_patch_saves_only_the_sent_field(): void
    {
        $u = $this->me();
        $this->actingAs($u)->patchJson(route('profile.patch'), ['height' => '165'])->assertOk()->assertJsonPath('saved', ['height']);
        $p = $u->profile->fresh();
        $this->assertSame(165, (int) $p->height);
        $this->assertSame('老師', $p->job);      // 沒送的欄位不動
        $this->assertSame('基督徒', $p->faith);   // 必填但沒送：不擋這次存檔
        $this->actingAs($u)->patchJson(route('profile.patch'), ['name' => '  新暱稱 '])->assertOk();
        $this->assertSame('新暱稱', $u->fresh()->name);
    }

    public function test_invalid_or_empty_required_is_422_and_not_saved(): void
    {
        $u = $this->me();
        $this->actingAs($u)->patchJson(route('profile.patch'), ['faith' => ''])->assertStatus(422)->assertJsonValidationErrors('faith');
        $this->assertSame('基督徒', $u->profile->fresh()->faith);
        $this->actingAs($u)->patchJson(route('profile.patch'), ['birth_month' => '2', 'birth_day' => '30'])->assertStatus(422)->assertJsonValidationErrors('birth_day');
        $this->actingAs($u)->patchJson(route('profile.patch'), ['gender' => '男', 'is_admin' => 1])->assertStatus(422); // 不認得的欄位不存
        $this->assertSame('女', $u->profile->fresh()->gender);
        $this->assertFalse((bool) $u->fresh()->is_admin);
    }

    public function test_intro_arrays_and_checkbox(): void
    {
        $u = $this->me();
        $this->actingAs($u)->patchJson(route('profile.intro.patch'), ['hobbies' => ['電視電影', '音樂欣賞']])->assertOk();
        $this->assertSame(['音樂欣賞', '電視電影'], $u->profile->fresh()->hobbies); // 照選項順序
        $this->actingAs($u)->patchJson(route('profile.intro.patch'), ['hobbies' => []])->assertStatus(422)->assertJsonValidationErrors('hobbies');
        $this->actingAs($u)->patchJson(route('profile.intro.patch'), ['hobbies' => ['音樂欣賞', '戶外活動', '電視電影', '泡茶聊天']])->assertStatus(422);
        $this->actingAs($u)->patchJson(route('profile.intro.patch'), ['church_visible' => true])->assertOk();
        $this->assertTrue((bool) $u->profile->fresh()->church_visible);
        $this->actingAs($u)->patchJson(route('profile.intro.patch'), ['intro' => '太短'])->assertStatus(422);
    }

    public function test_pages_have_autosave_hooks_and_guest_cannot_patch(): void
    {
        $u = $this->me();
        $this->actingAs($u)->get(route('profile.edit'))->assertSee('data-autosave="' . route('profile.patch') . '"', false)->assertSee('id="toast"', false);
        $this->actingAs($u)->get(route('profile.intro'))->assertSee('data-autosave="' . route('profile.intro.patch') . '"', false);
        auth()->logout();
        $this->patchJson(route('profile.patch'), ['height' => '170'])->assertUnauthorized();
    }

    public function test_settings_and_preferences_patch(): void
    {
        $u = $this->me();
        $this->actingAs($u)->patchJson(route('settings.patch'), ['notify_email' => false, 'is_active' => true, 'font_size' => 'large', 'font_family' => 'system', 'script' => 'hant'])
            ->assertOk()->assertJsonPath('reload', true)->assertJsonPath('note', null);
        $this->assertSame('large', $u->fresh()->font_size);
        $this->actingAs($u)->patchJson(route('settings.patch'), ['notify_email' => false, 'is_active' => false, 'font_size' => 'large', 'font_family' => 'system', 'script' => 'hant'])
            ->assertOk()->assertJsonPath('reload', false);
        $this->assertFalse((bool) $u->fresh()->is_active);
        $this->actingAs($u)->patchJson(route('settings.patch'), ['font_size' => 'huge', 'script' => 'hant'])->assertStatus(422);
        $this->actingAs($u)->get(route('center.account'))->assertDontSee('設定存好了'); // 即時存檔不留整頁提示

        $this->actingAs($u)->patchJson(route('center.preferences.patch'), ['pref_age_min' => '30', 'pref_age_max' => '40'])->assertOk();
        $this->assertSame(30, (int) $u->profile->fresh()->pref_age_min);
        $this->actingAs($u)->patchJson(route('center.preferences.patch'), ['pref_age_min' => '45', 'pref_age_max' => '40'])->assertStatus(422)->assertJsonValidationErrors('pref_age_max');
        $this->assertSame(30, (int) $u->profile->fresh()->pref_age_min);
    }
}
