<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// 補搬註冊日期與認證（熊叔叔 11/24 TG「甲 兩個都補」；Asana 1220841267386727）
// 舊站的表在測試庫另建（utf8mb3，跟 PushLegacyOutboxTest 一樣），不碰舊站開發庫
class BackfillLegacyExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $base = config('database.connections.' . config('database.default'));
        config(['database.connections.legacy' => array_merge($base, ['charset' => 'utf8mb3', 'collation' => 'utf8mb3_general_ci', 'strict' => false])]);
        DB::purge('legacy');
        $l = DB::connection('legacy');
        foreach (['basic', 'certification'] as $t) $l->statement("DROP TABLE IF EXISTS `{$t}`");
        $l->statement('CREATE TABLE basic (id INT PRIMARY KEY, join_date TIMESTAMP NULL) CHARSET utf8mb3');
        $l->statement('CREATE TABLE certification (id INT PRIMARY KEY, company_certification INT, company_name CHAR(50), company_url CHAR(50), company_email CHAR(50), company_timestamp TIMESTAMP NULL,
            id_certification INT, id_timestamp TIMESTAMP NULL, church_certification INT, church_timestamp TIMESTAMP NULL) CHARSET utf8mb3');
    }

    protected function tearDown(): void
    {
        $l = DB::connection('legacy');
        foreach (['basic', 'certification'] as $t) $l->statement("DROP TABLE IF EXISTS `{$t}`");
        parent::tearDown();
    }

    private function member(int $lid): User
    {
        $u = User::factory()->create(['legacy_id' => $lid]);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_join_date_copied_as_taipei_wall_time_and_idempotent(): void
    {
        $u = $this->member(1000701);
        $other = $this->member(1000702);
        $l = DB::connection('legacy');
        $l->table('basic')->insert([['id' => 1000701, 'join_date' => '2014-02-18 13:50:32'], ['id' => 1000702, 'join_date' => null], ['id' => 1999999, 'join_date' => '2015-01-01 00:00:00']]);
        $before = (string) $other->fresh()->created_at;

        $this->artisan('legacy:backfill-extras', ['--dry-run' => true])->expectsOutputToContain('要改 註冊日期 1 位')->assertSuccessful();
        $this->assertNotSame('2014-02-18 13:50:32', $u->fresh()->created_at->format('Y-m-d H:i:s'), 'dry-run 不寫');

        $this->artisan('legacy:backfill-extras')->expectsOutputToContain('已改 註冊日期 1 位')->assertSuccessful();
        $this->assertSame('2014-02-18 13:50:32', $u->fresh()->created_at->format('Y-m-d H:i:s'), '台北牆上時間原樣寫');
        $this->assertSame($before, (string) $other->fresh()->created_at, '舊站沒有日期的不動');
        $this->artisan('legacy:backfill-extras')->expectsOutputToContain('已改 註冊日期 0 位')->assertSuccessful();
        $this->assertSame(0, DB::table('legacy_outbox')->count(), '不排回寫');
    }

    public function test_approved_certifications_become_verifications_and_existing_ones_are_kept(): void
    {
        $a = $this->member(1000711);
        $b = $this->member(1000712);
        DB::table('verifications')->insert(['user_id' => $b->id, 'kind' => 'id', 'status' => 'pending', 'company' => '', 'company_url' => '', 'company_email' => '',
            'file_path' => 'x.jpg', 'reject_reason' => '', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('legacy')->table('certification')->insert([
            ['id' => 1000711, 'company_certification' => 3, 'company_name' => '協會', 'company_url' => 'https://ccnda.org', 'company_email' => 'a@ccnda.org', 'company_timestamp' => '2020-03-04 05:06:07',
                'id_certification' => 3, 'id_timestamp' => '2021-01-02 03:04:05', 'church_certification' => 1, 'church_timestamp' => null],
            ['id' => 1000712, 'company_certification' => 0, 'company_name' => '', 'company_url' => '', 'company_email' => '', 'company_timestamp' => null,
                'id_certification' => 3, 'id_timestamp' => '2019-05-05 05:05:05', 'church_certification' => 3, 'church_timestamp' => '2022-02-02 02:02:02'],
        ]);
        $this->artisan('legacy:backfill-extras')->expectsOutputToContain('認證 職業 1、身分 1、教會 1')->assertSuccessful();

        $job = DB::table('verifications')->where('user_id', $a->id)->where('kind', 'job')->first();
        $this->assertSame(['approved', '協會', 'https://ccnda.org', 'a@ccnda.org', '', '2020-03-04 05:06:07'],
            [$job->status, $job->company, $job->company_url, $job->company_email, $job->file_path, (string) $job->reviewed_at]);
        $this->assertSame('approved', DB::table('verifications')->where('user_id', $a->id)->where('kind', 'id')->value('status'));
        $this->assertNull(DB::table('verifications')->where('user_id', $a->id)->where('kind', 'church')->first(), '舊站審核中（1）不搬');
        $this->assertSame('pending', DB::table('verifications')->where('user_id', $b->id)->where('kind', 'id')->value('status'), '新版已有的不蓋');
        $this->assertSame('approved', DB::table('verifications')->where('user_id', $b->id)->where('kind', 'church')->value('status'));

        $this->artisan('legacy:backfill-extras')->expectsOutputToContain('認證 職業 0、身分 0、教會 0')->assertSuccessful();
        $this->assertSame(4, DB::table('verifications')->count(), '重跑不重複');
    }
}
