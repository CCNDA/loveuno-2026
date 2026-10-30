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
}
