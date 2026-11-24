<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\Friendship;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// 公測回寫：legacy_outbox → 舊站（Asana 1220564106252789）
// 舊站的表在測試庫裡另建一份（utf8mb3，跟舊站一樣），legacy 連線指到同一個測試庫；不碰 cora-lab 的舊站開發庫
class PushLegacyOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['loveuno.beta_audit' => true]);
        $base = config('database.connections.' . config('database.default'));
        config(['database.connections.legacy' => array_merge($base, ['charset' => 'utf8mb3', 'collation' => 'utf8mb3_general_ci', 'strict' => false])]);
        DB::purge('legacy');
        $l = DB::connection('legacy');
        foreach (['basic', 'profile', 'diary', 'relationship'] as $t) $l->statement("DROP TABLE IF EXISTS `{$t}`");
        $l->statement('CREATE TABLE basic (id INT PRIMARY KEY, email VARCHAR(50), password VARCHAR(50), nickname VARCHAR(20), status INT, modify_date TIMESTAMP NULL) CHARSET utf8mb3');
        $l->statement('CREATE TABLE profile (id INT PRIMARY KEY, sex VARCHAR(2), birthday DATE NULL, live_place VARCHAR(20), church VARCHAR(100), church_show TINYINT, job VARCHAR(20), intro TEXT, '
            . "hope_sex CHAR(20) NOT NULL DEFAULT '', hope_marriage CHAR(20) NOT NULL DEFAULT '', hope_religion CHAR(20) NOT NULL DEFAULT '', hope_live_place CHAR(20) NOT NULL DEFAULT '', "
            . 'hope_age1 INT NOT NULL DEFAULT 0, hope_age2 INT NOT NULL DEFAULT 0, hope_height1 INT NOT NULL DEFAULT 0, hope_height2 INT NOT NULL DEFAULT 0, hope_weight1 INT NOT NULL DEFAULT 0, hope_weight2 INT NOT NULL DEFAULT 0) CHARSET utf8mb3');
        $l->statement('CREATE TABLE diary (id INT AUTO_INCREMENT PRIMARY KEY, ownerid INT, subject VARCHAR(100), content TEXT, date DATE, status INT, pic VARCHAR(100), is_r2 TINYINT) CHARSET utf8mb3');
        $l->statement('CREATE TABLE relationship (id INT AUTO_INCREMENT PRIMARY KEY, sender INT, sendee INT, relationship INT) CHARSET utf8mb3');
    }

    protected function tearDown(): void
    {
        $l = DB::connection('legacy');
        foreach (['basic', 'profile', 'diary', 'relationship'] as $t) $l->statement("DROP TABLE IF EXISTS `{$t}`");
        parent::tearDown();
    }

    private function legacyMember(int $lid, array $over = []): User
    {
        $u = User::factory()->create(['legacy_id' => $lid] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990, 'marital' => '未婚']);
        DB::connection('legacy')->table('basic')->insert(['id' => $lid, 'email' => "m{$lid}@x", 'password' => 'plain', 'nickname' => '舊名', 'status' => 2]);
        DB::connection('legacy')->table('profile')->insert(['id' => $lid, 'sex' => '女', 'birthday' => '1990-05-06', 'live_place' => '台北', 'church' => '', 'church_show' => 0, 'job' => '', 'intro' => '']);
        DB::table('legacy_outbox')->delete();

        return $u;
    }

    private function push(): void
    {
        $this->artisan('legacy:push-outbox')->assertSuccessful();
    }

    public function test_nickname_and_profile_written_back_without_touching_password_or_birthday(): void
    {
        $u = $this->legacyMember(1000501);
        $u->forceFill(['name' => '新暱稱'])->save();
        $u->profile->update(['job' => '老師', 'intro' => "第一行\n第二行", 'church_visible' => true]);
        $this->push();
        $b = DB::connection('legacy')->table('basic')->where('id', 1000501)->first();
        $this->assertSame('新暱稱', $b->nickname);
        $this->assertSame('plain', $b->password, '密碼不動');
        $this->assertNotNull($b->modify_date);
        // 舊站 PHP 用台北時間寫 modify_date 字串：回寫也一樣（小布 11/21 審查 🟠3；原本寫 UTC、早 8 小時）
        $this->assertLessThan(180, abs(\Illuminate\Support\Carbon::parse($b->modify_date, 'Asia/Taipei')->diffInSeconds(now('Asia/Taipei'))));
        $p = DB::connection('legacy')->table('profile')->where('id', 1000501)->first();
        $this->assertSame('老師', $p->job);
        $this->assertSame('第一行<br>' . "\n" . '第二行', $p->intro);
        $this->assertSame(1, (int) $p->church_show);
        $this->assertSame('1990-05-06', $p->birthday, '生日不回寫');
        $this->assertSame(0, DB::table('legacy_outbox')->where('status', 'pending')->count());
    }

    // 熊叔叔 11/24「交友條件也要寫回」
    public function test_preferences_written_back_only_when_changed(): void
    {
        $u = $this->legacyMember(1000511);
        DB::connection('legacy')->table('profile')->where('id', 1000511)->update(['hope_sex' => '男', 'hope_age1' => 25, 'hope_age2' => 40, 'hope_religion' => '基督徒']);

        // 只改職業：舊站交友條件不動
        $u->profile->update(['job' => '護理師']);
        $this->push();
        $p = DB::connection('legacy')->table('profile')->where('id', 1000511)->first();
        $this->assertSame(['護理師', '男', 25, 40, '基督徒'], [$p->job, $p->hope_sex, (int) $p->hope_age1, (int) $p->hope_age2, $p->hope_religion]);

        // 改交友條件
        $u->profile->forceFill(['pref_gender' => '', 'pref_marital' => '未婚', 'pref_faith' => '慕道友', 'pref_place' => '台中市',
            'pref_age_min' => 28, 'pref_age_max' => 35, 'pref_height_min' => 160, 'pref_height_max' => null, 'pref_weight_min' => null, 'pref_weight_max' => null])->save();
        $this->push();
        $p = DB::connection('legacy')->table('profile')->where('id', 1000511)->first();
        $this->assertSame(['男', '未婚', '慕道友', '台中市'], [$p->hope_sex, $p->hope_marriage, $p->hope_religion, $p->hope_live_place], '性別沒改（本來就是空白）不寫');
        $this->assertSame([28, 35, 160, 220, 0, 0], array_map('intval', [$p->hope_age1, $p->hope_age2, $p->hope_height1, $p->hope_height2, $p->hope_weight1, $p->hope_weight2]));
        $this->assertSame(0, DB::table('legacy_outbox')->where('status', '!=', 'sent')->whereNot('status', 'skipped')->count());

        // 性別改成女，再改回不拘（空白）
        $u->profile->forceFill(['pref_gender' => '女'])->save();
        $this->push();
        $this->assertSame('女', DB::connection('legacy')->table('profile')->where('id', 1000511)->value('hope_sex'));
        $u->profile->forceFill(['pref_gender' => ''])->save();
        $this->push();
        $this->assertSame('不拘', DB::connection('legacy')->table('profile')->where('id', 1000511)->value('hope_sex'));
    }

    public function test_hope_fields_mapping(): void
    {
        $f = fn (array $p) => \App\Console\Commands\PushLegacyOutbox::hopeFields($p);
        $this->assertSame([], $f(['pref_gender' => '女']), '舊的 outbox 沒有 _changed：不寫');
        $this->assertSame(['hope_sex' => '女'], $f(['pref_gender' => '女', '_changed' => ['pref_gender']]));
        $this->assertSame(['hope_weight1' => 30, 'hope_weight2' => 60], $f(['pref_weight_max' => 60, '_changed' => ['pref_weight_max']]));
        $this->assertSame(['hope_age1' => 0, 'hope_age2' => 0], $f(['pref_age_min' => null, 'pref_age_max' => null, '_changed' => ['pref_age_min']]));
        $this->assertSame(['hope_live_place' => '□台北'], $f(['pref_place' => '😀台北', '_changed' => ['pref_place']]));
    }

    public function test_emoji_replaced_for_utf8mb3(): void
    {
        $u = $this->legacyMember(1000502);
        $u->forceFill(['name' => '小芯😀'])->save();
        $this->push();
        $this->assertSame('小芯□', DB::connection('legacy')->table('basic')->where('id', 1000502)->value('nickname'));
    }

    public function test_diary_create_update_delete_round_trip_and_no_loop(): void
    {
        $u = $this->legacyMember(1000503);
        $this->actingAs($u)->post('/diaries', ['title' => '新版寫的', 'body' => '內容', 'visibility' => 'public'])->assertRedirect();
        $d = Diary::where('user_id', $u->id)->firstOrFail();
        $this->push();
        $d->refresh();
        $this->assertSame('diary', $d->legacy_source);
        $old = DB::connection('legacy')->table('diary')->where('id', $d->legacy_id)->first();
        $this->assertSame(1000503, (int) $old->ownerid);
        $this->assertSame('新版寫的', $old->subject);
        $this->assertSame(0, (int) $old->status);
        $this->assertSame(0, DB::table('legacy_outbox')->where('status', 'pending')->count(), '存回舊站編號不會再排一次（防迴圈）');

        $d->update(['title' => '改過']);
        $this->push();
        $this->assertSame('改過', DB::connection('legacy')->table('diary')->where('id', $d->legacy_id)->value('subject'));
        $this->assertSame(1, DB::connection('legacy')->table('diary')->count(), '更新不會多插一篇');

        $lid = $d->legacy_id;
        $d->delete();
        $this->push();
        $this->assertSame(-1, (int) DB::connection('legacy')->table('diary')->where('id', $lid)->value('status'), '刪除 → 舊站隱藏，不真的刪');
    }

    public function test_diary_created_then_deleted_before_push_leaves_nothing_on_legacy(): void
    {
        $u = $this->legacyMember(1000504);
        $d = Diary::forceCreate(['user_id' => $u->id, 'title' => '馬上刪', 'body' => 'x', 'visibility' => 'public', 'published_on' => now()->toDateString()]);
        $d->delete();
        $this->push();
        $this->assertSame(0, DB::connection('legacy')->table('diary')->count());
    }

    public function test_pray_diary_from_legacy_not_written_to_diary_table(): void
    {
        $u = $this->legacyMember(1000505);
        $d = Diary::forceCreate(['user_id' => $u->id, 'title' => '舊站代禱', 'body' => 'x', 'visibility' => 'public', 'published_on' => now()->toDateString(), 'legacy_source' => 'pray', 'legacy_id' => 77]);
        $d->update(['title' => '改代禱']);
        $this->push();
        $this->assertSame(0, DB::connection('legacy')->table('diary')->count());
        $this->assertSame(0, DB::table('legacy_outbox')->where('status', 'pending')->count());
    }

    public function test_friendship_insert_level_and_delete(): void
    {
        $a = $this->legacyMember(1000506);
        $b = $this->legacyMember(1000507);
        $f = Friendship::forceCreate(['user_id' => $a->id, 'friend_id' => $b->id, 'level' => 1]);
        $this->push();
        $this->assertSame(1, (int) DB::connection('legacy')->table('relationship')->where('sender', 1000506)->where('sendee', 1000507)->value('relationship'));
        $f->forceFill(['level' => 3])->save();
        $this->push();
        $this->assertSame(1, DB::connection('legacy')->table('relationship')->count());
        $this->assertSame(3, (int) DB::connection('legacy')->table('relationship')->value('relationship'));
        $f->delete();
        $this->push();
        $this->assertSame(0, DB::connection('legacy')->table('relationship')->count());
    }

    public function test_new_only_account_skipped_and_errors_retry_then_fail(): void
    {
        $newOnly = User::factory()->create(['legacy_id' => null]);
        DB::table('legacy_outbox')->delete();
        $newOnly->forceFill(['name' => '只在新版'])->save();
        $this->push();
        $this->assertSame('skipped', DB::table('legacy_outbox')->value('status'));

        $u = $this->legacyMember(1000508);
        DB::connection('legacy')->statement('DROP TABLE basic');   // 讓回寫失敗
        $u->forceFill(['name' => '會失敗'])->save();
        for ($i = 1; $i <= 5; $i++) {
            $this->artisan('legacy:push-outbox')->assertFailed();
        }
        $row = DB::table('legacy_outbox')->where('model', 'User')->where('record_id', (string) $u->id)->first();
        $this->assertSame('failed', $row->status);
        $this->assertSame(5, (int) $row->attempts);
        $this->assertNotEmpty($row->last_error);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $u = $this->legacyMember(1000509);
        $u->forceFill(['name' => '試跑'])->save();
        $this->artisan('legacy:push-outbox', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('舊名', DB::connection('legacy')->table('basic')->where('id', 1000509)->value('nickname'));
        $this->assertSame('pending', DB::table('legacy_outbox')->value('status'));
    }

    // 🟠3（小布 11/21）同一筆前面有沒送成功的 → 後面較新的先不送，免得下一輪重送舊的把新內容蓋回去
    public function test_later_row_waits_for_earlier_failed_one(): void
    {
        $u = $this->legacyMember(1000511);
        $u->forceFill(['name' => '第一次改'])->save();
        $u->forceFill(['name' => '第二次改'])->save();
        DB::table('legacy_outbox')->where('model', 'User')->orderBy('id')->limit(1)->update(['status' => 'failed', 'attempts' => 5]);
        $this->push();
        $this->assertSame('舊名', DB::connection('legacy')->table('basic')->where('id', 1000511)->value('nickname'));
        $this->assertSame(['failed', 'pending'], DB::table('legacy_outbox')->where('model', 'User')->orderBy('id')->pluck('status')->all());
        // 前一筆處理掉（人工標 skipped）之後就放行
        DB::table('legacy_outbox')->where('status', 'failed')->update(['status' => 'skipped']);
        $this->push();
        $this->assertSame('第二次改', DB::connection('legacy')->table('basic')->where('id', 1000511)->value('nickname'));
    }

    // 🟠4（小布 11/21）日記新增重送：舊站已經有同一篇（上次插進去、存回編號前出錯）→ 沿用，不再插一篇
    public function test_diary_insert_retry_reuses_existing_legacy_row(): void
    {
        $u = $this->legacyMember(1000512);
        $d = Diary::forceCreate(['user_id' => $u->id, 'title' => '重送', 'body' => '內容', 'visibility' => 'public', 'published_on' => '2026-11-21']);
        $existing = DB::connection('legacy')->table('diary')->insertGetId(['ownerid' => 1000512, 'subject' => '重送', 'content' => '內容', 'date' => '2026-11-21', 'status' => 0, 'pic' => '', 'is_r2' => 0]);
        DB::table('legacy_outbox')->where('model', 'Diary')->update(['attempts' => 1]);   // 上一輪失敗過
        $this->push();
        $this->assertSame(1, DB::connection('legacy')->table('diary')->where('ownerid', 1000512)->count());
        $this->assertSame($existing, (int) $d->fresh()->legacy_id);
    }
}
