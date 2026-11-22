<?php

namespace Tests\Feature;

use App\Models\AlbumPhoto;
use App\Models\Profile;
use App\Models\User;
use App\Support\BetaChangeRecorder;
use App\Support\PhotoStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// 熊叔叔 11/22 TG 選甲「直接用 R2 原檔」：舊站照片不搬，新版存 legacy:<目錄>/<檔名>，從舊站 R2 公開網址讀；刪照片只刪紀錄、不刪舊站的檔
class LegacyR2PhotoTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://r2.example.test';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        BetaChangeRecorder::$importing = false;
        config(['loveuno.legacy_photo_url' => self::BASE]);
        Storage::fake('avatars');
        $this->dir = sys_get_temp_dir() . '/lu-r2-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*") ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function png(): string
    {
        $im = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function member(int $lid): User
    {
        $u = User::factory()->create(['legacy_id' => $lid]);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_legacy_ref_rejects_bad_paths(): void
    {
        $this->assertSame('legacy:member_photo/image/2019/a b.jpg', PhotoStore::legacyRef('member_photo/image', '/2019/a b.jpg'));
        $this->assertNull(PhotoStore::legacyRef('member_photo/image', '../h2019/.env.dev'));
        $this->assertNull(PhotoStore::legacyRef('h2019', 'func.php'), '不在清單的目錄');
        $this->assertNull(PhotoStore::legacyRef('diary_pic', "a\nb.jpg"));
        $this->assertNull(PhotoStore::legacyRef('diary_pic', 'a.jpg?x=1'));
        $this->assertNull(PhotoStore::legacyRef('diary_pic', ''));
    }

    public function test_album_photo_served_from_legacy_r2_and_delete_keeps_original(): void
    {
        Http::fake([self::BASE . '/member_photo/image/2019/a%20b.jpg' => Http::response($this->png(), 200), '*' => Http::response('nope', 404)]);
        $me = $this->member(1000901);
        $other = $this->member(1000902);
        $ph = new AlbumPhoto;
        $ph->forceFill(['user_id' => $me->id, 'path' => 'legacy:member_photo/image/2019/a b.jpg', 'status' => 'approved'])->save();

        $this->actingAs($other)->get("/album/{$ph->id}")->assertOk()->assertHeader('Content-Type', 'image/png');
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::BASE . '/member_photo/image/2019/a%20b.jpg' && $r->method() === 'GET');

        // 沒設定網址、R2 回不是圖片 → 404，不會把別的東西當照片送出去
        config(['loveuno.legacy_photo_url' => '']);
        $this->actingAs($other)->get("/album/{$ph->id}")->assertNotFound();
        config(['loveuno.legacy_photo_url' => self::BASE]);
        $ph->forceFill(['path' => 'legacy:member_photo/image/missing.jpg'])->save();
        $this->actingAs($other)->get("/album/{$ph->id}")->assertNotFound();

        // 刪除：只刪紀錄；新版沒有那個桶的寫入權限，也不會對 R2 發出刪除
        $this->actingAs($me)->delete("/me/album/{$ph->id}")->assertRedirect();
        $this->assertSame(0, AlbumPhoto::count());
        Http::assertNotSent(fn (HttpRequest $r) => $r->method() === 'DELETE');
    }

    public function test_avatar_import_with_photo_ref_stores_path_without_download(): void
    {
        Http::fake();
        $row = fn (int $lid, string $photo) => ['legacy_id' => $lid, 'status' => 2, 'email' => "m{$lid}@example.com", 'password_hash' => password_hash('x', PASSWORD_BCRYPT),
            'nickname' => '舊站會員', 'gender' => '女', 'birth_year' => 1990, 'live_place' => '台北市', 'church' => '', 'church_visible' => false,
            'job' => '', 'intro' => '', 'photo' => $photo];
        file_put_contents("{$this->dir}/export.json", json_encode(['members' => [
            $row(1000903, 'legacy:member_photo/image/2020/me.jpg'),
            $row(1000904, 'legacy:member_photo/image/../../h2019/.env.dev'),
            $row(1000905, 'legacy:h2019/func.php'),
        ]], JSON_UNESCAPED_UNICODE));
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();

        $this->assertSame('legacy:member_photo/image/2020/me.jpg', User::where('legacy_id', 1000903)->first()->profile->photo_path);
        $this->assertNull(User::where('legacy_id', 1000904)->first()->profile->photo_path, '有 .. 不收');
        $this->assertNull(User::where('legacy_id', 1000905)->first()->profile->photo_path, '不是照片目錄不收');
        $this->assertSame([], Storage::disk('avatars')->allFiles(), '沒有另存任何檔案');
        Http::assertNothingSent();
    }

    public function test_album_import_with_photo_ref(): void
    {
        $u = $this->member(1000906);
        file_put_contents("{$this->dir}/content_manifest.json", json_encode(['since' => null, 'diary_owners' => null, 'kinds' => ['album'], 'counts' => []]));
        file_put_contents("{$this->dir}/album.jsonl", json_encode(['legacy_id' => 5001, 'owner' => 1000906, 'image' => 'legacy:member_photo/image/x/1.jpg']) . "\n"
            . json_encode(['legacy_id' => 5002, 'owner' => 1000906, 'image' => 'legacy:member_photo/image/../2.jpg']) . "\n");
        $this->artisan('legacy:import-content', ['dir' => $this->dir])->assertSuccessful();

        $this->assertSame(['legacy:member_photo/image/x/1.jpg'], AlbumPhoto::where('user_id', $u->id)->pluck('path')->all());
        $this->assertSame([], Storage::disk('avatars')->allFiles());
    }

    // 熊叔叔 11/22 TG 選甲「舊站通過的直接通過」
    public function test_avatar_approved_in_legacy_stays_approved(): void
    {
        $row = fn (int $lid, ?bool $approved) => ['legacy_id' => $lid, 'status' => 2, 'email' => "m{$lid}@example.com", 'password_hash' => password_hash('x', PASSWORD_BCRYPT),
            'nickname' => '舊站會員', 'gender' => '女', 'birth_year' => 1990, 'live_place' => '台北市', 'church' => '', 'church_visible' => false,
            'job' => '', 'intro' => '', 'photo' => "legacy:member_photo/image/{$lid}.jpg"] + ($approved === null ? [] : ['photo_approved' => $approved]);
        file_put_contents("{$this->dir}/export.json", json_encode(['members' => [$row(1000911, true), $row(1000912, false), $row(1000913, null)]], JSON_UNESCAPED_UNICODE));
        $this->artisan('legacy:import-export', ['dir' => $this->dir])->assertSuccessful();

        $st = fn (int $lid) => User::where('legacy_id', $lid)->first()->profile->photo_status;
        $this->assertSame('approved', $st(1000911), '舊站通過 → 直接通過');
        $this->assertSame('pending', $st(1000912), '舊站審核中 → 待審');
        $this->assertSame('pending', $st(1000913), '舊匯出檔沒有這欄 → 待審');

        $viewer = $this->member(1000914);
        Http::fake(['*' => Http::response($this->png(), 200)]);
        $this->actingAs($viewer)->get(route('members.show', User::where('legacy_id', 1000911)->first()))->assertOk();
    }
}
