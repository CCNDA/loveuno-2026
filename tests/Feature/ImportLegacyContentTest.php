<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportLegacyContentTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('avatars');
        $this->dir = sys_get_temp_dir() . '/lu2026_content_test_' . getmypid();
        @mkdir($this->dir . '/images', 0700, true);
        file_put_contents($this->dir . '/content_manifest.json', '{"kinds":["diary","pray"]}');
    }

    protected function tearDown(): void
    {
        foreach (array_merge(glob($this->dir . '/*.*') ?: [], glob($this->dir . '/images/*') ?: []) as $f) {
            @unlink($f);
        }
        @rmdir($this->dir . '/images');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function jsonl(string $name, array $rows): void
    {
        file_put_contents("{$this->dir}/{$name}.jsonl", implode("\n", array_map(fn ($r) => json_encode($r, JSON_UNESCAPED_UNICODE), $rows)) . "\n");
    }

    public function test_imports_diaries_prayers_and_replies_once(): void
    {
        $a = User::factory()->create(['legacy_id' => 1000001]);
        $b = User::factory()->create(['legacy_id' => 1000002]);
        $img = imagecreatetruecolor(1600, 1000);
        ob_start();
        imagejpeg($img);
        file_put_contents("{$this->dir}/images/diary_11.img", ob_get_clean());

        $this->jsonl('diary', [
            ['legacy_id' => 11, 'owner' => 1000001, 'title' => '今天', 'body' => "第一行\n第二行 😀", 'date' => '2019-05-01', 'status' => 0, 'image' => 'images/diary_11.img'],
            ['legacy_id' => 12, 'owner' => 1000001, 'title' => '藏起來的', 'body' => '後台隱藏', 'date' => '2019-05-02', 'status' => -1, 'image' => null],
            ['legacy_id' => 13, 'owner' => 1999999, 'title' => '沒搬的人', 'body' => 'x', 'date' => '2019-05-03', 'status' => 0, 'image' => null],
        ]);
        $this->jsonl('diary_reply', [
            ['legacy_id' => 101, 'parent' => 11, 'owner' => 1000002, 'status' => 0, 'body' => '加油！', 'at' => '2019-05-01 20:00:00'],
            ['legacy_id' => 102, 'parent' => 11, 'owner' => 1000002, 'status' => -1, 'body' => '被藏的', 'at' => '2019-05-01 21:00:00'],
            ['legacy_id' => 103, 'parent' => 13, 'owner' => 1000002, 'status' => 0, 'body' => '日記沒搬', 'at' => '2019-05-01 21:00:00'],
            ['legacy_id' => 104, 'parent' => 11, 'owner' => 1000002, 'status' => 0, 'body' => str_repeat('長', 600), 'at' => '2019-05-02 08:00:00'],
        ]);
        $this->jsonl('pray', [['legacy_id' => 21, 'owner' => 1000002, 'status' => 0, 'title' => '請為工作代禱', 'body' => '面試', 'at' => '2020-01-02 09:00:00']]);
        $this->jsonl('pray_reply', [['legacy_id' => 201, 'parent' => 21, 'owner' => 1000001, 'status' => 0, 'body' => '為你禱告', 'at' => '2020-01-02 10:00:00']]);

        $this->artisan('legacy:import-content', ['dir' => $this->dir])->expectsOutputToContain('作者沒搬進來 1')->assertSuccessful();

        $d = Diary::where('legacy_source', 'diary')->where('legacy_id', 11)->sole();
        $this->assertSame([$a->id, 'public', false, '2019-05-01'], [$d->user_id, $d->visibility, (bool) $d->is_prayer, $d->published_on->toDateString()]);
        $this->assertSame("第一行\n第二行 😀", $d->body);
        $this->assertNotNull($d->image_path);
        [$w] = getimagesizefromstring(Storage::disk('avatars')->get($d->image_path));
        $this->assertSame(1200, $w);
        $this->assertSame('private', Diary::where('legacy_id', 12)->value('visibility')); // 後台隱藏 → 只有自己
        $this->assertSame(0, Diary::where('legacy_id', 13)->count());

        $this->assertSame(2, DB::table('diary_comments')->where('diary_id', $d->id)->count()); // 101、104（102 隱藏、103 日記沒搬）
        $this->assertSame(500, mb_strlen(DB::table('diary_comments')->where('legacy_id', 104)->value('body')));

        $p = Diary::where('legacy_source', 'pray')->sole();
        $this->assertTrue((bool) $p->is_prayer);
        $this->assertSame($b->id, $p->user_id);
        $this->assertSame(1, DB::table('diary_comments')->where('diary_id', $p->id)->where('user_id', $a->id)->count());

        // 重跑：不重複
        $before = [Diary::count(), DB::table('diary_comments')->count()];
        $this->artisan('legacy:import-content', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame($before, [Diary::count(), DB::table('diary_comments')->count()]);

        // 新版看得到：別人看得到公開的，看不到隱藏的
        $this->actingAs($b)->get("/diaries/{$d->id}")->assertOk()->assertSee('第二行');
    }

    public function test_dry_run_writes_nothing(): void
    {
        User::factory()->create(['legacy_id' => 1000001]);
        $this->jsonl('diary', [['legacy_id' => 11, 'owner' => 1000001, 'title' => 't', 'body' => 'b', 'date' => '2019-05-01', 'status' => 0, 'image' => null]]);
        $this->artisan('legacy:import-content', ['dir' => $this->dir, '--dry-run' => true])->expectsOutputToContain('可以匯入 1')->assertSuccessful();
        $this->assertSame(0, Diary::count());
    }

    public function test_imports_friends_guestbook_album_dates_testimonies(): void
    {
        $a = User::factory()->create(['legacy_id' => 1000001]);
        $b = User::factory()->create(['legacy_id' => 1000002]);
        foreach ([[$a, '男'], [$b, '女']] as [$u, $g]) {
            \App\Models\Profile::create(['user_id' => $u->id, 'gender' => $g, 'birth_year' => 1990]);
        }
        $jpg = function (string $name) {
            $img = imagecreatetruecolor(800, 600);
            ob_start();
            imagejpeg($img);
            file_put_contents("{$this->dir}/images/{$name}.img", ob_get_clean());
        };
        foreach (range(1, 10) as $n) {
            $jpg("album_{$n}");
        }
        $jpg('testi_1');

        $this->jsonl('friend', [['legacy_id' => 1, 'a' => 1000002, 'b' => 1000001, 'level' => 2], ['legacy_id' => 2, 'a' => 1000001, 'b' => 1999999, 'level' => 1]]);
        $this->jsonl('guestbook', [
            ['legacy_id' => 1, 'from' => 1000001, 'to' => 1000002, 'body' => '你好', 'at' => '2018-01-01 10:00:00', 'private' => false, 'reply' => '你好呀', 'reply_at' => '2018-01-02 10:00:00'],
            ['legacy_id' => 2, 'from' => 1000002, 'to' => 1000001, 'body' => '私密', 'at' => '2018-01-03 10:00:00', 'private' => true, 'reply' => '', 'reply_at' => null],
        ]);
        $this->jsonl('album', array_map(fn ($n) => ['legacy_id' => $n, 'owner' => 1000001, 'image' => "images/album_{$n}.img"], range(1, 10)));
        $this->jsonl('date', [
            ['legacy_id' => 1, 'from' => 1000001, 'to' => 1000002, 'date' => '2019-03-03', 'time' => '9:30 AM', 'place' => '台北靈糧堂 台北市大安區', 'activity' => '主日聚會', 'single' => '希望你單獨參加', 'message' => '一起去', 'at' => '2019-03-01 10:00:00', 'result' => 3, 'result_at' => '2019-03-04 10:00:00', 'result_msg' => '很開心'],
            ['legacy_id' => 2, 'from' => 1000002, 'to' => 1000001, 'date' => '2019-04-04', 'time' => '2:00 PM', 'place' => '', 'activity' => '奇怪的', 'single' => '歡迎邀請家人朋友一起', 'message' => '', 'at' => '2019-04-01 10:00:00', 'result' => -1, 'result_at' => '2019-04-02 10:00:00', 'result_msg' => '那天有事'],
        ]);
        $this->jsonl('testi', [['legacy_id' => 1, 'owner' => 1000001, 'male' => 1000001, 'female' => 1000002, 'status' => 1, 'title' => '我們結婚了', 'body' => '感謝主', 'at' => '2020-06-01 10:00:00', 'image' => 'images/testi_1.img']]);

        $this->artisan('legacy:import-content', ['dir' => $this->dir])->expectsOutputToContain('相簿滿了（9 張） 1')->assertSuccessful();

        $this->assertSame(2, \App\Models\Friendship::between($a, $b)->level);
        $this->assertSame(1, DB::table('friendships')->count());
        $notes = DB::table('notes')->orderBy('created_at')->get();
        $this->assertSame([[$a->id, $b->id, '你好'], [$b->id, $a->id, '你好呀'], [$b->id, $a->id, '私密']], $notes->map(fn ($n) => [$n->sender_id, $n->recipient_id, $n->body])->all());
        $this->assertNotNull($notes[0]->read_at);
        $this->assertSame(9, DB::table('album_photos')->where('user_id', $a->id)->where('status', 'approved')->count());

        $m1 = \App\Models\Meetup::where('legacy_id', 1)->sole();
        $this->assertSame(['attended', '09:30', 'sunday', 'alone', '很開心'], [$m1->status, $m1->meet_time, $m1->activity, $m1->company, $m1->closing_note]);
        $m2 = \App\Models\Meetup::where('legacy_id', 2)->sole();
        $this->assertSame(['declined', '14:00', 'special', 'friends', '那天有事', '（舊站沒有填）'], [$m2->status, $m2->meet_time, $m2->activity, $m2->company, $m2->reply, $m2->place]);

        $story = \App\Models\LoveStory::sole();
        $this->assertSame(['approved', $a->id, $b->id], [$story->status, $story->male_id, $story->female_id]);
        $this->assertNotNull($story->photo_path);

        // 重跑不重複
        $before = [DB::table('notes')->count(), DB::table('album_photos')->count(), DB::table('meetups')->count(), DB::table('love_stories')->count(), DB::table('friendships')->count()];
        $this->artisan('legacy:import-content', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame($before, [DB::table('notes')->count(), DB::table('album_photos')->count(), DB::table('meetups')->count(), DB::table('love_stories')->count(), DB::table('friendships')->count()]);

        // 新版畫面看得到
        $this->actingAs($b)->get('/notes/' . $a->id)->assertOk()->assertSee('你好呀');
        $this->get('/love-stories/' . $story->id)->assertOk()->assertSee('感謝主');
    }

    public function test_time_and_activity_conversion(): void
    {
        $t = fn ($s) => \App\Console\Commands\ImportLegacyContent::time24($s);
        $this->assertSame(['00:00', '12:00', '12:30', '23:30', '09:30', '10:00'], [$t('12:00 AM'), $t('12:00 PM'), $t('12:45 PM'), $t('11:30 PM'), $t('9:30 am'), $t('亂寫')]);
        $k = fn ($s) => \App\Console\Commands\ImportLegacyContent::activityKey($s);
        $this->assertSame(['group', 'evangelism', 'agape', 'special'], [$k('小組聚會'), $k('佈道會'), $k('愛宴'), $k('')]);
    }
}
