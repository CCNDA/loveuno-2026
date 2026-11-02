<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 認識朋友的條件搜尋（上線前檢測表 D-01；條件照舊站 member/search.php）
class MemberSearchTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, array $profile = [], array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        // $profile 放左邊：PHP 的 + 是左邊優先，要讓傳進來的值蓋掉預設值
        // forceCreate：photo_status 不在 fillable（照片只能走上傳＋審核）
        Profile::forceCreate($profile + ['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'live_place' => '台北市', 'marital' => '未婚', 'faith' => '基督徒']);

        return $u;
    }

    private function search(User $me, array $q)
    {
        return $this->actingAs($me)->get('/members?' . http_build_query($q));
    }

    public function test_each_condition(): void
    {
        $me = $this->member('我', ['gender' => '男']);
        $y = (int) now()->format('Y');
        $this->member('三十歲', ['birth_year' => $y - 30]);
        $this->member('四十五歲', ['birth_year' => $y - 45]);
        $this->member('離婚的', ['marital' => '離婚']);
        $this->member('尋道的人', ['faith' => '慕道友']);
        $this->member('有照片', ['photo_path' => 'avatars/x.jpg', 'photo_status' => 'approved']);
        $this->member('照片待審', ['photo_path' => 'avatars/y.jpg', 'photo_status' => 'pending']);
        $job = $this->member('職業認證過');
        Verification::forceCreate(['user_id' => $job->id, 'kind' => 'job', 'status' => 'approved']);
        $pend = $this->member('職業認證審核中');
        Verification::forceCreate(['user_id' => $pend->id, 'kind' => 'job', 'status' => 'pending']);
        $this->member('小芯百分比%');

        $this->search($me, ['age_min' => 28, 'age_max' => 32])->assertSee('三十歲')->assertDontSee('四十五歲');
        $this->search($me, ['age_min' => 40])->assertSee('四十五歲')->assertDontSee('三十歲');
        $this->search($me, ['marital' => '離婚'])->assertSee('離婚的')->assertDontSee('三十歲');
        $this->search($me, ['faith' => '慕道友'])->assertSee('尋道的人')->assertDontSee('離婚的');
        $this->search($me, ['photo' => 1])->assertSee('有照片')->assertDontSee('照片待審')->assertDontSee('三十歲');
        $this->search($me, ['verified' => ['job']])->assertSee('職業認證過')->assertDontSee('職業認證審核中');
        $this->search($me, ['name' => '十歲'])->assertSee('三十歲')->assertDontSee('四十五歲');
        // % 是一般字元，不是萬用字元
        $this->search($me, ['name' => '%'])->assertSee('小芯百分比%')->assertDontSee('三十歲');
        // 條件合在一起是「而且」
        $this->search($me, ['age_min' => 28, 'age_max' => 32, 'marital' => '離婚'])->assertSee('沒有符合條件的會員');
    }

    public function test_more_conditions_open_when_used_and_invalid_input_rejected(): void
    {
        $me = $this->member('我', ['gender' => '男']);
        $this->search($me, [])->assertOk()->assertSee('更多條件')->assertDontSee('<details class="more-filters" open', false);
        $this->search($me, ['faith' => '基督徒'])->assertSee('<details class="more-filters" open', false)->assertSee('清除條件');
        $this->search($me, ['age_min' => 40, 'age_max' => 30])->assertSessionHasErrors('age_max');
        $this->search($me, ['marital' => '已婚'])->assertSessionHasErrors('marital'); // 選單只有會員自己能選的
        $this->search($me, ['verified' => ['phone']])->assertSessionHasErrors('verified.0');
    }
}
