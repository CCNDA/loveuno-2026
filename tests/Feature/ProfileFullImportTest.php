<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BetaChangeRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 舊站 profile 全欄位帶進新版（熊叔叔 11/22 內測：交友條件、自介、日記…都沒帶過來；「直接切換，舊資料都要在」）
class ProfileFullImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        BetaChangeRecorder::$importing = false;
        $this->dir = sys_get_temp_dir() . '/lu-pf-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700);
    }

    protected function tearDown(): void
    {
        @unlink("{$this->dir}/export.json");
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function legacyProfile(array $over = []): array
    {
        return array_merge([
            'realname' => '王小明', 'homephone' => '02-12345678', 'birthday' => '1990-05-06',
            'marriage' => '未婚', 'kids' => '0', 'religion' => '基督徒', 'education' => '大學', 'blood' => 'O',
            'smoke' => '從來不抽', 'drink' => '淺嚐', 'height' => '175', 'weight' => '70',
            'language1' => '中文/普通话', 'language2' => '英文', 'language3' => '火星文',
            'hobby1' => '音樂欣賞', 'hobby2' => '戶外活動', 'hobby3' => '',
            'sports1' => '羽毛球', 'sports2' => '慢跑', 'sports3' => '',
            'bible' => '當將你的事交託耶和華<br>並倚靠他',
            'hope_sex' => '女', 'hope_age1' => '25', 'hope_age2' => '35', 'hope_marriage' => '不拘', 'hope_live_place' => '台北市',
            'hope_height1' => '150', 'hope_height2' => '170', 'hope_weight1' => '40', 'hope_weight2' => '60', 'hope_religion' => '基督徒',
            'cvcurrent' => '單身中，尋找有緣人', 'cvpast' => '2~3次戀愛經驗', 'cvfeelhomo' => '看神的帶領', 'cvmembreak' => '亂寫的值',
            'cvnote' => '期待<br>一起服事',
        ], $over);
    }

    private function export(int $lid, array $profile): void
    {
        file_put_contents("{$this->dir}/export.json", json_encode(['members' => [[
            'legacy_id' => $lid, 'status' => 2, 'email' => "m{$lid}@example.com", 'password_hash' => password_hash('x', PASSWORD_BCRYPT),
            'nickname' => '舊站會員', 'gender' => '男', 'birth_year' => 1990, 'live_place' => '台北市', 'church' => '', 'church_visible' => false,
            'job' => '工程', 'intro' => '自我介紹', 'photo' => null, 'legacy_profile' => $profile,
        ]]], JSON_UNESCAPED_UNICODE));
    }

    public function test_new_member_gets_all_profile_fields(): void
    {
        $this->export(1000801, $this->legacyProfile());
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();
        $p = User::where('legacy_id', 1000801)->first()->profile;
        $this->assertSame('王小明', $p->real_name);
        $this->assertSame(5, (int) $p->birth_month);
        $this->assertSame(6, (int) $p->birth_day);
        $this->assertSame(['未婚', '0', '基督徒', '大學', 'O', '從來不抽', '淺嚐'], [$p->marital, $p->children, $p->faith, $p->education, $p->blood_type, $p->smoking, $p->drinking]);
        $this->assertSame([175, 70], [(int) $p->height, (int) $p->weight]);
        $this->assertSame(['中文/普通話', '英文'], $p->languages, '簡體寫法統一、不在選項裡的（火星文）不收');
        $this->assertSame(['音樂欣賞', '戶外活動'], $p->hobbies);
        $this->assertSame(['羽毛球', '慢跑'], $p->sports);
        $this->assertSame("當將你的事交託耶和華\n並倚靠他", $p->verse_text);
        // 交友條件
        $this->assertSame(['女', '台北市', '', '基督徒'], [$p->pref_gender, $p->pref_place, $p->pref_marital, $p->pref_faith], '「不拘」＝沒設定');
        $this->assertSame([25, 35, 150, 170, 40, 60], array_map('intval', [$p->pref_age_min, $p->pref_age_max, $p->pref_height_min, $p->pref_height_max, $p->pref_weight_min, $p->pref_weight_max]));
        // 戀愛觀
        $this->assertSame('單身中，尋找有緣人', $p->love_history['cvcurrent']);
        $this->assertSame('看神的帶領', $p->love_history['cvfeelhomo']);
        $this->assertArrayNotHasKey('cvmembreak', $p->love_history, '不在選項裡的值不收');
        $this->assertSame("期待\n一起服事", $p->love_history['cvnote']);
    }

    public function test_out_of_range_and_unknown_values_left_empty(): void
    {
        $this->export(1000802, $this->legacyProfile(['marriage' => '保留', 'hope_age1' => '10', 'hope_age2' => '30', 'hope_height1' => '180', 'hope_height2' => '160', 'hope_sex' => '不拘', 'birthday' => '0000-00-00']));
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();
        $p = User::where('legacy_id', 1000802)->first()->profile;
        $this->assertSame('', $p->marital, '「保留」新版沒有這個選項');
        $this->assertNull($p->pref_age_min, '10 歲低於新版下限 18');
        $this->assertSame(30, (int) $p->pref_age_max);
        $this->assertNull($p->pref_height_min, '最小比最大大 → 兩個都不收');
        $this->assertNull($p->pref_height_max);
        $this->assertSame('', $p->pref_gender);
        $this->assertNull($p->birth_month);
    }

    public function test_update_from_legacy_fills_fields_for_already_migrated(): void
    {
        config(['loveuno.beta_audit' => true]);
        // 先用舊格式（沒有 legacy_profile）匯進來，就像 11/22 中午那兩個帳號
        file_put_contents("{$this->dir}/export.json", json_encode(['members' => [[
            'legacy_id' => 1000803, 'status' => 2, 'email' => 'm1000803@example.com', 'password_hash' => password_hash('x', PASSWORD_BCRYPT),
            'nickname' => '舊站會員', 'gender' => '男', 'birth_year' => 1990, 'live_place' => '台北市', 'church' => '', 'church_visible' => false,
            'job' => '', 'intro' => '自介', 'photo' => null]]], JSON_UNESCAPED_UNICODE));
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame('', User::where('legacy_id', 1000803)->first()->profile->pref_gender);
        $this->export(1000803, $this->legacyProfile());
        $this->artisan('legacy:import-export', ['dir' => $this->dir, '--update-from-legacy' => true])->assertSuccessful();
        $p = User::where('legacy_id', 1000803)->first()->profile;
        $this->assertSame('女', $p->pref_gender);
        $this->assertSame('基督徒', $p->faith);
        $this->assertSame('單身中，尋找有緣人', $p->love_history['cvcurrent']);
    }

    // 小布 11/22 審查：匯進來的值要過得了新版表單驗證（不然會員之後改資料會被擋），只有文字的金句也要看得到
    public function test_imported_values_pass_profile_form_validation_and_verse_shows(): void
    {
        $this->export(1000804, $this->legacyProfile(['height' => '100', 'homephone' => '02-1234567 分機12', 'birthday' => '1990-02-30',
            'bible' => '當將你的事交託耶和華']));
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();
        $u = User::where('legacy_id', 1000804)->first();
        $p = $u->profile;
        $this->assertNull($p->height, '100 低於表單下限 120');
        $this->assertSame('', $p->home_phone, '含「分機」不符表單 regex');
        $this->assertNull($p->birth_month, '2 月 30 日 checkdate 不過');

        // 用 ProfileController 自己的規則驗一次「會員原樣送出目前的資料」
        $ctl = new \App\Http\Controllers\ProfileController;
        $data = ['name' => $u->name];
        foreach (array_keys((new \ReflectionClassConstant($ctl, 'PROFILE_FIELDS'))->getValue()) as $k) {
            $v = $p->{$k};
            if ($v !== null && $v !== '' && $v !== []) {
                $data[$k] = $v;
            }
        }
        $req = \Illuminate\Http\Request::create('/me', 'PUT', $data);
        $req->setUserResolver(fn () => $u);
        $m = new \ReflectionMethod($ctl, 'profileRules');
        $m->setAccessible(true);
        [$rules] = $m->invoke($ctl, $req);
        $v = \Illuminate\Support\Facades\Validator::make($data, $rules);
        $this->assertFalse($v->fails(), '匯入後原樣送出不能被擋：' . json_encode($v->errors()->toArray(), JSON_UNESCAPED_UNICODE));

        $this->actingAs($u)->get(route('members.show', $u))->assertOk()->assertSee('當將你的事交託耶和華');
    }
}
