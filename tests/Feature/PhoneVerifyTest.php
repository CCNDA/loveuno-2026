<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PhoneVerifyTest extends TestCase
{
    use RefreshDatabase;

    private function newMember(): User
    {
        $u = User::factory()->phoneUnverified()->create(['name' => '新會員']);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    // 從 log 抓出這次寄的認證碼（SMS_DRIVER=log）
    private function sendAndCatchCode(User $u, string $phone = '0912345678'): string
    {
        $code = null;
        Log::listen(function ($e) use (&$code) {
            if (preg_match('/認證碼 (\d{6})/u', (string) ($e->context['text'] ?? ''), $m)) {
                $code = $m[1];
            }
        });
        $this->actingAs($u)->post('/phone/send', ['phone' => $phone])->assertRedirect('/phone/verify');
        $this->assertNotNull($code);

        return $code;
    }

    public function test_member_pages_need_phone_verified(): void
    {
        $u = $this->newMember();
        $this->actingAs($u)->get('/diaries')->assertRedirect('/phone/verify');
        $this->actingAs($u)->get('/')->assertRedirect('/phone/verify');
        $this->actingAs($u)->get('/phone/verify')->assertOk()->assertSee('手機認證')->assertSee('support@loveuno.com');
    }

    public function test_send_code_then_verify(): void
    {
        $u = $this->newMember();
        $code = $this->sendAndCatchCode($u, '0912-345-678');
        $u->refresh();
        $this->assertSame('0912345678', $u->phone_pending);
        $this->assertNotSame($code, $u->phone_code_hash); // 只存雜湊
        $this->actingAs($u)->get('/phone/verify')->assertSee('輸入認證碼')->assertSee('0912345678');

        $wrong = $code === '000000' ? '111111' : '000000';
        $this->actingAs($u)->post('/phone/verify', ['code' => $wrong])->assertSessionHasErrors(['code' => '認證碼不對，還可以再試 4 次。']);
        $this->actingAs($u)->post('/phone/verify', ['code' => $code])->assertRedirect('/diaries');
        $u->refresh();
        $this->assertSame('0912345678', $u->phone);
        $this->assertNotNull($u->phone_verified_at);
        $this->assertNull($u->phone_code_hash);
        $this->actingAs($u)->get('/diaries')->assertOk();
        $this->assertArrayNotHasKey('phone', $u->toArray());
    }

    // 「完成手機認證 +10」在認證當下就入帳，不必等打開愛積點頁（小霏 11/4 帳本：手機 11/2 認證、點數 11/4 才進來）
    public function test_phone_points_given_at_verification(): void
    {
        $u = $this->newMember();
        $code = $this->sendAndCatchCode($u);
        $this->assertSame(0, \DB::table('point_transactions')->where('user_id', $u->id)->where('ref', 'once')->where('type', 'phone')->count());
        $this->actingAs($u)->post('/phone/verify', ['code' => $code])->assertRedirect('/diaries');
        $this->assertSame(1, \DB::table('point_transactions')->where('user_id', $u->id)->where('ref', 'once')->where('type', 'phone')->count());
    }

    public function test_validation_expiry_attempts_and_rate_limit(): void
    {
        $u = $this->newMember();
        $this->actingAs($u)->post('/phone/send', ['phone' => '02-12345678'])->assertSessionHasErrors('phone');
        $code = $this->sendAndCatchCode($u);
        // 60 秒內不能再寄
        $this->actingAs($u)->post('/phone/send', ['phone' => '0912345678'])->assertSessionHasErrors('phone');

        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($u)->post('/phone/verify', ['code' => $wrong]);
        }
        $this->actingAs($u)->post('/phone/verify', ['code' => $code])->assertSessionHasErrors(['code' => '輸入錯太多次了，請重新寄一次認證碼。']);

        // 過期
        $u->forceFill(['phone_code_attempts' => 0, 'phone_code_hash' => Hash::make('123456'), 'phone_code_expires_at' => now()->subMinute()])->save();
        $this->actingAs($u)->post('/phone/verify', ['code' => '123456'])->assertSessionHasErrors(['code' => '認證碼已經過期了，請重新寄一次。']);
        $this->assertNull($u->fresh()->phone_verified_at);
    }

    public function test_one_phone_one_account(): void
    {
        User::factory()->create(['phone' => '0912345678']);
        $u = $this->newMember();
        $this->actingAs($u)->post('/phone/send', ['phone' => '0912345678'])
            ->assertSessionHasErrors(['phone' => '這支手機已經用在別的帳號了。如果是您本人的，請寫信跟我們聯繫。']);
    }

    public function test_twsms_driver_posts_and_handles_errors(): void
    {
        config(['loveuno.sms.driver' => 'twsms', 'loveuno.sms.twsms_username' => 'u', 'loveuno.sms.twsms_password' => 'p']);
        Http::fake(['api.twsms.com/*' => Http::sequence()->push(['code' => '00020', 'text' => 'no credit'])->push(['code' => '00000', 'msgid' => '1'])]);
        $u = $this->newMember();

        $this->actingAs($u)->post('/phone/send', ['phone' => '0912345678'])->assertSessionHasErrors(['phone' => '簡訊發送額度不足，請寫信跟我們聯繫。']);
        $this->assertNull($u->fresh()->phone_pending); // 寄不出去就不動狀態

        $this->travel(2)->minutes();
        $this->actingAs($u)->post('/phone/send', ['phone' => '0912345678'])->assertRedirect('/phone/verify');
        Http::assertSent(fn ($req) => $req->url() === 'https://api.twsms.com/json/sms_send.php'
            && $req['mobile'] === '0912345678' && str_contains($req['message'], 'CCNDA簡訊：愛諾園手機認證碼') && (int) $req['expirytime'] === 600);
    }

    public function test_verified_member_changes_number_with_password(): void
    {
        $u = User::factory()->create(['phone' => '0911111111']);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);
        $this->actingAs($u)->get('/phone/verify')->assertRedirect('/me/verify');
        $this->actingAs($u)->get('/center/account')->assertSee('0911***111');
        $this->actingAs($u)->post('/phone/send', ['phone' => '0922222222'])->assertSessionHasErrors('current_password');
        $this->actingAs($u)->post('/phone/send', ['phone' => '0922222222', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');

        $code = null;
        Log::listen(function ($e) use (&$code) {
            if (preg_match('/認證碼 (\d{6})/u', (string) ($e->context['text'] ?? ''), $m)) {
                $code = $m[1];
            }
        });
        $this->actingAs($u)->post('/phone/send', ['phone' => '0922222222', 'current_password' => 'password'])->assertRedirect('/phone/verify');
        $this->actingAs($u)->get('/phone/verify')->assertSee('更換手機號碼');
        $this->actingAs($u)->get('/diaries')->assertOk(); // 換號碼中，原本的認證還在
        $this->actingAs($u)->post('/phone/verify', ['code' => $code])->assertRedirect('/me/verify');
        $this->assertSame('0922222222', $u->fresh()->phone);
    }

    public function test_unverified_phone_members_are_hidden_from_others(): void
    {
        $hidden = $this->newMember();
        $me = User::factory()->create();
        Profile::create(['user_id' => $me->id, 'gender' => '男', 'birth_year' => 1990]);
        $this->actingAs($me)->get('/members')->assertDontSee(route('members.show', $hidden), false);
        $this->actingAs($me)->get("/notes/{$hidden->id}")->assertNotFound();
    }

    public function test_per_number_limit_and_masked_log(): void
    {
        config(['loveuno.sms.driver' => 'twsms', 'loveuno.sms.twsms_username' => 'u', 'loveuno.sms.twsms_password' => 'p']);
        Http::fake(['api.twsms.com/*' => Http::response(['code' => '00031'])]);
        $logged = [];
        Log::listen(function ($e) use (&$logged) {
            $logged[] = json_encode($e->context, JSON_UNESCAPED_UNICODE);
        });
        foreach (range(1, 3) as $i) {
            $u = $this->newMember();
            $this->actingAs($u)->post('/phone/send', ['phone' => '0933333333']);
        }
        $u = $this->newMember();
        $this->actingAs($u)->post('/phone/send', ['phone' => '0933333333'])->assertSessionHasErrors('phone');
        $this->assertStringContainsString('寄太多次', session('errors')->first('phone'));
        Http::assertSentCount(3);
        $this->assertStringNotContainsString('0933333333', implode(' ', $logged));
    }

    public function test_production_refuses_log_driver(): void
    {
        // 直接測 Sms（整個請求切到 production 時，測試的 CSRF 豁免也會失效）
        $this->app['env'] = 'production';
        $r = \App\Support\Sms::send('0912345678', 'x', 600);
        $this->assertFalse($r['ok']);
        $this->assertSame('簡訊服務還沒設定好，請寫信跟我們聯繫。', $r['message']);
    }

    public function test_foreign_numbers_like_old_site(): void
    {
        $this->assertSame(['+852-91234567', null], \App\Support\Phone::normalize('852', '091234567')); // 開頭的 0 拿掉
        $this->assertSame(['0912345678', null], \App\Support\Phone::normalize('886', '0912-345-678'));
        $this->assertNull(\App\Support\Phone::normalize('852', '12345')[0]);   // 太短
        $this->assertNull(\App\Support\Phone::normalize('999', '91234567')[0]); // 沒有這個國碼
        $x = User::factory()->phoneUnverified()->create();
        $this->actingAs($x)->post('/phone/send', ['phone_country' => '999', 'phone' => '91234567'])->assertSessionHasErrors(['phone_country' => '請選擇國碼。']);
        $this->flushSession(); // 上一個請求的 old() 不要帶到下一個
        $this->assertSame('85291234567', \App\Support\Phone::toTwsms('+852-91234567'));
        $this->assertSame('0912345678', \App\Support\Phone::toTwsms('0912345678'));

        $u = User::factory()->phoneUnverified()->create(['name' => '香港會員']);
        \App\Models\Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'live_place' => '香港']);
        $this->actingAs($u)->get('/phone/verify')->assertSee('value="852" selected', false); // 居住地是香港，先選好
        Log::spy();
        $this->actingAs($u)->post('/phone/send', ['phone_country' => '852', 'phone' => '91234567'])->assertRedirect('/phone/verify');
        Log::shouldHaveReceived('info')->withArgs(fn ($m, $ctx = []) => ($ctx['mobile'] ?? null) === '85291234567');
        $this->assertSame('+852-91234567', $u->fresh()->phone_pending);

        // 同一支號碼不同寫法也算同一支
        $other = User::factory()->phoneUnverified()->create();
        User::whereKey($u->id)->update(['phone' => '+852-91234567']);
        $this->actingAs($other)->post('/phone/send', ['phone_country' => '852', 'phone' => '091234567'])
            ->assertSessionHasErrors(['phone' => '這支手機已經用在別的帳號了。如果是您本人的，請寫信跟我們聯繫。']);
    }

    public function test_expires_after_twelve_months(): void
    {
        $u = User::factory()->create(['phone' => '0911111111', 'phone_verified_at' => now()->subMonths(12)->subDay()]);
        $fresh = User::factory()->create(['phone' => '0922222222', 'phone_verified_at' => now()->subMonths(11)]);
        $this->assertFalse($u->hasValidPhone());
        $this->assertTrue($fresh->hasValidPhone());
        $this->assertSame([$fresh->id], User::whereKey([$u->id, $fresh->id])->phoneVerified()->pluck('id')->all());

        // 登入就要重新認證；號碼留著預填、也還擋別人
        $this->actingAs($u)->get('/diaries')->assertRedirect('/phone/verify')->assertSessionHas('status', fn ($s) => str_contains($s, '超過 12 個月'));
        $this->assertNull($u->fresh()->phone_verified_at);
        $this->assertSame('0911111111', $u->fresh()->phone);
        $this->actingAs($u)->get('/phone/verify')->assertSee('請重新認證一次')->assertSee('value="0911111111"', false);
        $u = $u->fresh();
        $code = $this->sendAndCatchCode($u, '0911111111');
        $this->actingAs($u)->post('/phone/verify', ['code' => $code])->assertRedirect('/diaries');
        $this->assertTrue($u->fresh()->hasValidPhone());

        // 每天的排程：很久沒登入的也拿掉
        $old = User::factory()->create(['phone' => '0933333333', 'phone_verified_at' => now()->subMonths(13)]);
        $this->artisan('phone:expire')->assertSuccessful();
        $this->assertNull($old->fresh()->phone_verified_at);
        $this->assertNotNull($fresh->fresh()->phone_verified_at);
        $this->actingAs($fresh)->get('/center/account')->assertSee('認證到 ' . now()->subMonths(11)->addMonthsNoOverflow(12)->format('Y/n/j'));
    }

    public function test_unverified_profile_is_hidden_even_by_url(): void
    {
        $new = User::factory()->phoneUnverified()->create(['name' => '新會員']);
        \App\Models\Profile::create(['user_id' => $new->id, 'gender' => '女', 'birth_year' => 1990]);
        $other = User::factory()->create();
        \App\Models\Profile::create(['user_id' => $other->id, 'gender' => '男', 'birth_year' => 1990]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($other)->get("/members/{$new->id}")->assertNotFound();
        $this->actingAs($admin)->get("/members/{$new->id}")->assertOk();
        $new->forceFill(['phone' => '0955555555', 'phone_verified_at' => now()])->save();
        $this->actingAs($other)->get("/members/{$new->id}")->assertOk();
    }
}
