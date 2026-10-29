<?php

namespace Tests\Feature;

use App\Models\AlbumPhoto;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlbumTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_upload_review_and_visibility(): void
    {
        Storage::fake('avatars');
        $me = $this->member('我');
        $other = $this->member('別人');
        $admin = $this->member('管理員', ['is_admin' => true]);

        $this->actingAs($me)->get('/me/album')->assertOk()->assertSee('相簿照片上限為 9 張');
        $this->actingAs($me)->post('/me/album', ['photos' => [UploadedFile::fake()->image('a.jpg', 2000, 1500)]])
            ->assertRedirect('/me/album')->assertSessionHas('status', fn ($s) => str_contains($s, '審核'));
        $ph = AlbumPhoto::firstOrFail();
        $this->assertSame('pending', $ph->status);
        [$w] = getimagesizefromstring(Storage::disk('avatars')->get($ph->path));
        $this->assertSame(1200, $w);

        // 審核中：本人、管理員看得到；別人看不到
        $this->actingAs($me)->get("/album/{$ph->id}")->assertOk();
        $this->actingAs($admin)->get("/album/{$ph->id}")->assertOk();
        $this->actingAs($other)->get("/album/{$ph->id}")->assertNotFound();
        $this->actingAs($other)->get("/members/{$me->id}?tab=album")->assertSee('相簿還沒有照片');

        $this->actingAs($admin)->get('/admin/photos')->assertSee('相簿照片')->assertSee(route('album.show', $ph), false);
        $this->actingAs($admin)->post("/admin/album/{$ph->id}", ['decision' => 'approve'])->assertRedirect('/admin/photos');
        $this->actingAs($other)->get("/album/{$ph->id}")->assertOk();
        $this->actingAs($other)->get("/members/{$me->id}?tab=album")->assertSee(route('album.show', $ph), false);

        // 封鎖就看不到
        DB::table('blocks')->insert(['blocker_id' => $me->id, 'blocked_id' => $other->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($other)->get("/album/{$ph->id}")->assertNotFound();
    }

    public function test_max_nine_and_delete(): void
    {
        Storage::fake('avatars');
        $me = $this->member('我');
        for ($i = 0; $i < AlbumPhoto::MAX; $i++) {
            $this->actingAs($me)->post('/me/album', ['photos' => [UploadedFile::fake()->image("p{$i}.png", 400, 400)]])->assertRedirect();
        }
        $this->assertSame(9, AlbumPhoto::count());
        $this->actingAs($me)->get('/me/album')->assertSee('相簿滿了');
        $this->actingAs($me)->post('/me/album', ['photos' => [UploadedFile::fake()->image('x.png', 400, 400)]])
            ->assertSessionHasErrors(['photos' => '相簿最多 9 張，請先刪掉一張再放。']);
        $this->assertSame(9, AlbumPhoto::count());

        $ph = AlbumPhoto::first();
        $other = $this->member('別人');
        $this->actingAs($other)->delete("/me/album/{$ph->id}")->assertNotFound(); // 不能刪別人的
        $this->actingAs($me)->delete("/me/album/{$ph->id}")->assertRedirect('/me/album');
        Storage::disk('avatars')->assertMissing($ph->path);
        $this->assertSame(8, AlbumPhoto::count());
    }

    public function test_reject_deletes_and_validation(): void
    {
        Storage::fake('avatars');
        $me = $this->member('我');
        $admin = $this->member('管理員', ['is_admin' => true]);
        $this->actingAs($me)->post('/me/album', ['photos' => [UploadedFile::fake()->create('a.gif', 10, 'image/gif')]])->assertSessionHasErrors('photos.0');
        $this->actingAs($me)->post('/me/album', ['photos' => [UploadedFile::fake()->image('a.jpg', 100, 100)]])->assertSessionHasErrors('photos.0'); // 太小

        $this->actingAs($me)->post('/me/album', ['photos' => [UploadedFile::fake()->image('a.jpg', 800, 800)]]);
        $ph = AlbumPhoto::firstOrFail();
        $this->actingAs($me)->post("/admin/album/{$ph->id}", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($admin)->post("/admin/album/{$ph->id}", ['decision' => 'reject'])->assertRedirect('/admin/photos');
        Storage::disk('avatars')->assertMissing($ph->path);
        // 留一格「沒有通過」給會員看，不佔名額；會員可以刪掉
        $this->assertSame('rejected', $ph->fresh()->status);
        $this->actingAs($me)->get('/me/album')->assertSee('這張沒有通過管理員審核');
        $this->assertSame(0, AlbumPhoto::countedFor($me->id));
        $this->actingAs($me)->delete("/me/album/{$ph->id}")->assertRedirect();
        $this->assertSame(0, AlbumPhoto::count());
    }

    public function test_upload_several_at_once_within_limit(): void
    {
        Storage::fake('avatars');
        $me = $this->member('我');
        $files = fn (int $n) => array_map(fn ($i) => UploadedFile::fake()->image("m{$i}.jpg", 400, 400), range(1, $n));
        $this->actingAs($me)->post('/me/album', ['photos' => $files(7)])->assertSessionHas('status', fn ($s) => str_contains($s, '7 張'));
        $this->actingAs($me)->post('/me/album', ['photos' => $files(3)])->assertSessionHasErrors(['photos' => '相簿還可以放 2 張，這次選太多了。']);
        $this->assertSame(7, AlbumPhoto::count());
        $this->assertCount(7, Storage::disk('avatars')->allFiles('album')); // 失敗的那批沒有留下檔案
    }
}
