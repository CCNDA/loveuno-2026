<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Support\Points;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Support\NotEnoughPoints;
use Tests\TestCase;

class PointsTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $profile = [], array $user = []): User
    {
        $u = User::factory()->create($user);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990] + $profile);

        return $u;
    }

    private function balance(User $u): int
    {
        return (int) DB::table('users')->where('id', $u->id)->value('points');
    }

    public function test_daily_login_once_per_day(): void
    {
        $u = $this->member();
        $this->actingAs($u)->get('/');
        $this->actingAs($u)->get('/diaries?mine=1');
        $this->assertSame(1, $this->balance($u));

        $this->travel(1)->days();
        $this->actingAs($u)->get('/');
        $this->assertSame(2, $this->balance($u));
        $this->assertSame(2, DB::table('point_transactions')->where('type', 'daily_login')->count());
    }

    public function test_profile_tasks_award_once(): void
    {
        $u = $this->member();
        $this->actingAs($u)->put('/me', $this->profileForm(['name' => '新朋友', 'live_place' => '台北市', 'job' => '老師']))->assertRedirect();
        $this->actingAs($u)->put('/me', $this->profileForm(['name' => '新朋友', 'live_place' => '台中市', 'job' => '老師']))->assertRedirect();
        // 完成戀愛履歷建檔＝問卷選擇題都答了（熊叔叔 10/29「3A」）
        $love = collect(\App\Support\LoveHistory::QUESTIONS)->map(fn ($q) => $q[1][0])->all();
        $this->actingAs($u)->put('/me/love', $love)->assertRedirect();
        $this->actingAs($u)->put('/center/preferences', ['pref_gender' => '男'])->assertRedirect();

        $types = DB::table('point_transactions')->where('user_id', $u->id)->pluck('amount', 'type')->all();
        $this->assertSame(20, (int) $types['profile']);
        $this->assertSame(10, (int) $types['love']);
        $this->assertSame(10, (int) $types['preferences']);
        $this->assertSame(1 + 20 + 10 + 10 + 10, $this->balance($u)); // 含每日登入、手機認證（factory 預設手機已驗證）
    }

    public function test_photo_approval_awards_twenty(): void
    {
        $u = $this->member();
        $u->profile->forceFill(['photo_path' => 'x.jpg', 'photo_status' => 'pending', 'photo_updated_at' => now()])->save(); // 不在 Fillable
        $admin = $this->member([], ['is_admin' => true]);
        $this->actingAs($admin)->post("/admin/photos/{$u->id}", ['decision' => 'approve', 'photo' => 'x.jpg', 'kind' => 'photo'])->assertRedirect();
        $this->assertSame(20, (int) DB::table('point_transactions')->where('user_id', $u->id)->where('type', 'photo')->value('amount'));
    }

    public function test_diary_gives_three_once_a_day(): void
    {
        $u = $this->member();
        foreach (['一', '二'] as $t) {
            $this->actingAs($u)->post('/diaries', ['title' => $t, 'body' => '內容', 'visibility' => 'public']);
        }
        $this->assertSame(1, DB::table('point_transactions')->where('user_id', $u->id)->where('type', 'diary')->count());
        $this->assertSame(1 + 3, $this->balance($u));
    }

    public function test_spend_checks_balance_and_never_goes_negative(): void
    {
        $u = $this->member();
        Points::award($u, 'test', 'x', 5, '測試');
        Points::spend($u, 'gift', 'g1');
        $this->assertSame(2, $this->balance($u));
        try {
            Points::spend($u, 'gift', 'g2');
            $this->fail('應該要丟例外');
        } catch (NotEnoughPoints) {
        }
        $this->assertSame(2, $this->balance($u));
        $this->assertSame(-3, (int) DB::table('point_transactions')->where('ref', 'g1')->value('amount'));
        $this->assertNull(DB::table('point_transactions')->where('ref', 'g2')->first());
    }

    public function test_points_page_shows_tasks_backfills_and_records(): void
    {
        // 以前就填好的人（舊站匯入、示範會員）：第一次打開這頁時補給
        $u = $this->member(['live_place' => '台北市', 'job' => '工程師', 'faith' => '基督徒', 'marital' => '未婚', 'children' => '0',
            'education' => '大學', 'blood_type' => 'O', 'smoking' => '從來不抽', 'drinking' => '從來不喝']);
        $this->actingAs($u)->get('/center/points')->assertOk()
            ->assertSee('我目前的愛諾積點')->assertSee('<strong>31</strong>', false) // 基本資料 20＋手機認證 10＋每日登入 1
            ->assertSeeInOrder(['完成基本資料建檔', '+20', '任務已完成', '完成希望對象建檔', '+10', '立即前往'])
            ->assertSee('送禮物給好友')->assertSee('邀請約會見面');

        $this->actingAs($u)->get('/center/points?tab=records')->assertOk()
            ->assertSeeInOrder(['使用紀錄', '完成基本資料建檔', '+20'])->assertSee('每日登入')->assertSee('總計')
            ->assertSee('<td>31</td>', false); // 最新一筆的總計＝目前餘額
    }
}
