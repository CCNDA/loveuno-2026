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

    public function test_cover_preset_patch(): void
    {
        $u = $this->me();
        $this->actingAs($u)->patchJson(route('cover.preset.patch'), ['cover_preset' => '4'])->assertOk();
        $this->assertSame(4, (int) $u->profile->fresh()->cover_preset);
        $this->actingAs($u)->patchJson(route('cover.preset.patch'), ['cover_preset' => '12'])->assertStatus(422);
        $this->assertSame(4, (int) $u->profile->fresh()->cover_preset);
    }

    // 小布 11/5 審查：伺服器不能信任前端的分組（只送一組裡的一欄）
    public function test_partial_group_patches_are_checked_against_saved_values(): void
    {
        $u = $this->me();
        \Illuminate\Support\Facades\DB::table('bible_verses')->insert([
            ['book' => 43, 'chapter' => 3, 'verse' => 16, 'text' => '神愛世人'],
            ['book' => 43, 'chapter' => 3, 'verse' => 17, 'text' => '因為神差他的兒子降世'],
        ]);
        $this->actingAs($u)->patchJson(route('profile.intro.patch'), ['verse_book' => '約翰福音', 'verse_chapter' => 3, 'verse_verse' => 16])->assertOk();
        // 1. 只送「節」：要跟資料庫裡的書卷、章一起驗，經文跟著換
        $this->actingAs($u)->patchJson(route('profile.intro.patch'), ['verse_verse' => 99])->assertStatus(422);
        $this->assertSame(16, (int) $u->profile->fresh()->verse_verse);
        $this->actingAs($u)->patchJson(route('profile.intro.patch'), ['verse_verse' => 17])->assertOk();
        $this->assertSame('因為神差他的兒子降世', $u->profile->fresh()->verse_text);
        // 2. 只送「日」：要跟資料庫裡的月份一起檢查
        $this->actingAs($u)->patchJson(route('profile.patch'), ['birth_month' => 2, 'birth_day' => 10])->assertOk();
        $this->actingAs($u)->patchJson(route('profile.patch'), ['birth_day' => 31])->assertStatus(422)->assertJsonValidationErrors('birth_day');
        $this->assertSame(10, (int) $u->profile->fresh()->birth_day);
        // 3. 交友條件只送一欄：其他條件不能被清掉
        $this->actingAs($u)->patchJson(route('center.preferences.patch'), ['pref_age_min' => 30, 'pref_age_max' => 40, 'pref_faith' => '基督徒'])->assertOk();
        $this->actingAs($u)->patchJson(route('center.preferences.patch'), ['pref_age_max' => 45])->assertOk();
        $p = $u->profile->fresh();
        $this->assertSame([30, 45, '基督徒'], [(int) $p->pref_age_min, (int) $p->pref_age_max, $p->pref_faith]);
        $this->actingAs($u)->patchJson(route('center.preferences.patch'), ['pref_age_max' => 20])->assertStatus(422); // 跟資料庫的下限 30 比
    }

    // 小鹿 11/6 審查：設定只送一欄時，其他設定不能被重設
    public function test_partial_settings_patch_keeps_other_settings(): void
    {
        $u = $this->me();
        $this->actingAs($u)->patchJson(route('settings.patch'), ['notify_email' => true, 'is_active' => true, 'font_size' => 'large', 'font_family' => 'huninn', 'script' => 'hans'])->assertOk();
        $this->actingAs($u)->patchJson(route('settings.patch'), ['font_size' => 'small'])->assertOk();
        $u->refresh();
        $this->assertSame(['small', 'huninn', 'hans', true, true], [$u->font_size, $u->font_family, $u->script, (bool) $u->notify_email, (bool) $u->is_active]);
        $this->actingAs($u)->patchJson(route('settings.patch'), ['is_active' => false])->assertOk();
        $u->refresh();
        $this->assertSame(['small', 'huninn', false, true], [$u->font_size, $u->font_family, (bool) $u->is_active, (bool) $u->notify_email]);
        $this->actingAs($u)->patchJson(route('settings.patch'), ['nothing' => 1])->assertStatus(422);
    }
}
