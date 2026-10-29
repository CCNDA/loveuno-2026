<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DemoSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_public_and_has_skip_link_and_version(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('跳到主要內容')
            ->assertSee('lang="zh-Hant-TW"', false)
            ->assertSee('版本 ' . trim(file_get_contents(base_path('VERSION'))));
    }

    public function test_guest_is_sent_to_login_for_member_pages(): void
    {
        $user = User::factory()->create();
        $diary = Diary::factory()->for($user)->create();

        foreach (['/diaries', "/diaries/{$diary->id}", "/members/{$user->id}", '/me'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_login_with_correct_password(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => 'secret-pass']);

        $this->post('/login', ['email' => 'A@Example.com', 'password' => 'secret-pass'])
            ->assertRedirect('/diaries');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_gives_one_generic_message(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'secret-pass']);

        $this->from('/login')->post('/login', ['email' => 'a@example.com', 'password' => 'nope'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email' => '電子郵件或密碼不正確。']);
        $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors(['email' => '電子郵件或密碼不正確。']);
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'secret-pass']);
        RateLimiter::clear('a@example.com|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'a@example.com', 'password' => 'nope']);
        }
        // 第 6 次就算密碼對也不讓進
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'secret-pass'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_private_diary_is_hidden_from_others_but_visible_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $public = Diary::factory()->for($owner)->create(['title' => '公開的那篇']);
        $private = Diary::factory()->for($owner)->private()->create(['title' => '私密的那篇']);

        $this->actingAs($other)->get('/diaries')->assertSee('公開的那篇')->assertDontSee('私密的那篇');
        $this->actingAs($other)->get("/diaries/{$private->id}")->assertNotFound();
        $this->actingAs($other)->get("/members/{$owner->id}")->assertDontSee('私密的那篇');

        $this->actingAs($owner)->get('/diaries')->assertSee('私密的那篇')->assertSee('只有自己看得到');
        $this->actingAs($owner)->get("/diaries/{$private->id}")->assertOk();
        $this->actingAs($owner)->get("/diaries/{$public->id}")->assertOk();
    }

    public function test_diary_body_is_escaped(): void
    {
        $user = User::factory()->create();
        $diary = Diary::factory()->for($user)->create(['body' => '<script>alert(1)</script>']);

        $this->actingAs($user)->get("/diaries/{$diary->id}")
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    // 舊站 chkemail 會擋含「+」的 email（已知 bug），新版要能用
    public function test_email_with_plus_can_log_in(): void
    {
        $user = User::factory()->create(['email' => 'amy+church@example.com', 'password' => 'secret-pass']);

        $this->post('/login', ['email' => 'amy+church@example.com', 'password' => 'secret-pass'])
            ->assertRedirect('/diaries');
        $this->assertAuthenticatedAs($user);
    }

    public function test_one_ip_cannot_try_many_emails(): void
    {
        RateLimiter::clear('ip|127.0.0.1');
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', ['email' => "u{$i}@example.com", 'password' => 'nope']);
        }
        User::factory()->create(['email' => 'real@example.com', 'password' => 'secret-pass']);
        $this->post('/login', ['email' => 'real@example.com', 'password' => 'secret-pass'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_redirect_keeps_https_behind_local_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'test-loveuno-2026.ps194.net', 'X-Forwarded-Port' => '443'])
            ->get('/diaries')
            ->assertRedirect('https://test-loveuno-2026.ps194.net/login');
    }

    public function test_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_import_hashes_legacy_password_and_maps_profile(): void
    {
        $this->makeLegacyTables();
        \DB::connection('legacy')->table('basic')->insert([
            ['id' => 1000010, 'email' => ' Old@Example.com ', 'password' => 'plain1234', 'nickname' => '舊會員'],
            ['id' => 1000011, 'email' => '', 'password' => 'x', 'nickname' => '沒有信箱'],
        ]);
        \DB::connection('legacy')->table('profile')->insert([
            ['id' => 1000010, 'sex' => '女', 'birthday' => '1990-05-01', 'live_place' => '台北市',
                'church' => '某某教會', 'church_show' => 0, 'job' => '老師', 'intro' => '你好'],
        ]);

        $this->artisan('legacy:import-accounts')->assertSuccessful();

        $user = User::where('legacy_id', 1000010)->firstOrFail();
        $this->assertTrue($user->is_demo); // 測試帳號在正式開放前要跟示範資料一起刪
        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('舊會員', $user->name);
        $this->assertNotSame('plain1234', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('plain1234', $user->password));
        $this->assertSame('女', $user->profile->gender);
        $this->assertSame('', $user->profile->church); // church_show=0 就不帶過來
        $this->assertNull(User::where('legacy_id', 1000011)->first());

        // 重跑不會多一筆
        $this->artisan('legacy:import-accounts')->assertSuccessful();
        $this->assertSame(1, User::where('legacy_id', 1000010)->count());

        // 匯入的帳號可以用舊密碼登入
        $this->post('/login', ['email' => 'old@example.com', 'password' => 'plain1234'])->assertRedirect('/diaries');
    }

    public function test_import_refuses_non_test_database(): void
    {
        config(['database.connections.legacy.database' => 'loveuno_member']);
        $this->artisan('legacy:import-accounts')->assertFailed();
    }

    // 測試時把 legacy 連線指到測試庫，建最小的 basic／profile
    private function makeLegacyTables(): void
    {
        config(['database.connections.legacy' => config('database.connections.' . config('database.default'))]);
        \DB::purge('legacy');
        $s = Schema::connection('legacy');
        $s->dropIfExists('basic');
        $s->dropIfExists('profile');
        $s->create('basic', function ($t) {
            $t->unsignedInteger('id')->primary();
            $t->string('email', 50)->default('');
            $t->string('password', 12)->default('');
            $t->string('nickname', 50)->default('');
        });
        $s->create('profile', function ($t) {
            $t->unsignedInteger('id')->primary();
            $t->string('sex', 5)->default('');
            $t->date('birthday')->nullable();
            $t->string('live_place', 20)->default('');
            $t->text('church')->nullable();
            $t->integer('church_show')->default(0);
            $t->string('job', 20)->default('');
            $t->text('intro')->nullable();
        });
        $this->beforeApplicationDestroyed(function () use ($s) {
            $s->dropIfExists('basic');
            $s->dropIfExists('profile');
        });
    }
}
