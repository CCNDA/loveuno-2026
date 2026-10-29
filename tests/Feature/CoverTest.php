<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('avatars');
    }

    private function member(string $name, bool $admin = false): User
    {
        $u = User::factory()->create(['name' => $name]);
        $u->forceFill(['is_admin' => $admin])->save();
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        return $u->fresh();
    }

    public function test_cover_upload_is_3_to_1_and_needs_review(): void
    {
        $u = $this->member('本人');
        $other = $this->member('別人');
        $admin = $this->member('管理員', true);
        $this->actingAs($u)->get('/me/cover')->assertOk()->assertSee('我的封面');
        $this->actingAs($u)->post('/me/cover', ['photo' => UploadedFile::fake()->image('c.jpg', 1600, 900)])->assertRedirect('/me/cover');

        $p = $u->profile->fresh();
        $this->assertSame('pending', $p->cover_status);
        $info = getimagesizefromstring(Storage::disk('avatars')->get($p->cover_path));
        $this->assertSame([1200, 400], [$info[0], $info[1]]);
        $this->assertNull($p->photo_path); // 跟大頭照分開

        $this->actingAs($other)->get("/covers/{$u->id}")->assertNotFound();
        $this->actingAs($u)->get("/covers/{$u->id}")->assertOk();
        $this->actingAs($admin)->get('/admin/photos')->assertSee('封面')->assertSee('本人');
        $this->actingAs($admin)->post("/admin/photos/{$u->id}", ['decision' => 'approve', 'photo' => $p->cover_path, 'kind' => 'cover'])->assertRedirect('/admin/photos');
        $this->assertSame('approved', $p->fresh()->cover_status);
        $this->actingAs($other)->get("/covers/{$u->id}")->assertOk();
        $this->actingAs($other)->get("/members/{$u->id}")->assertSee("/covers/{$u->id}", false);
    }

    public function test_cover_too_small_rejected_and_delete(): void
    {
        $u = $this->member('本人');
        $this->actingAs($u)->post('/me/cover', ['photo' => UploadedFile::fake()->image('s.jpg', 800, 300)])->assertSessionHasErrors('photo'); // 寬不到 900
        $this->actingAs($u)->post('/me/cover', ['photo' => UploadedFile::fake()->image('c.jpg', 1200, 400)]);
        $path = $u->profile->fresh()->cover_path;
        $this->actingAs($u)->delete('/me/cover')->assertRedirect('/me/cover');
        Storage::disk('avatars')->assertMissing($path);
        $this->assertNull($u->profile->fresh()->cover_path);
    }

    public function test_cover_reject_deletes_and_swap_during_review_is_caught(): void
    {
        $u = $this->member('本人');
        $admin = $this->member('管理員', true);
        $this->actingAs($u)->post('/me/cover', ['photo' => UploadedFile::fake()->image('a.jpg', 1200, 400)]);
        $seen = $u->profile->fresh()->cover_path;
        $this->actingAs($u)->post('/me/cover', ['photo' => UploadedFile::fake()->image('b.jpg', 1200, 400)]);
        // 管理員看到的是舊的那張：不套用
        $this->actingAs($admin)->post("/admin/photos/{$u->id}", ['decision' => 'approve', 'photo' => $seen, 'kind' => 'cover']);
        $this->assertSame('pending', $u->profile->fresh()->cover_status);
        // 對最新的那張按不通過：刪檔
        $now = $u->profile->fresh()->cover_path;
        $this->actingAs($admin)->post("/admin/photos/{$u->id}", ['decision' => 'reject', 'photo' => $now, 'kind' => 'cover']);
        Storage::disk('avatars')->assertMissing($now);
        $this->assertSame('rejected', $u->profile->fresh()->cover_status);
        // 亂填 kind 被擋
        $this->actingAs($admin)->post("/admin/photos/{$u->id}", ['decision' => 'approve', 'photo' => 'x', 'kind' => 'hack'])->assertSessionHasErrors('kind');
    }

    public function test_cover_guest_and_both_block_directions(): void
    {
        $u = $this->member('本人');
        $b = $this->member('另一位');
        $u->profile->forceFill(['cover_path' => 'c.jpg', 'cover_status' => 'approved'])->save();
        Storage::disk('avatars')->put('c.jpg', 'x');
        $this->get("/covers/{$u->id}")->assertRedirect('/login');
        // 本人封鎖別人：被封鎖的人看不到本人的封面（另一個測試）；別人封鎖本人：封鎖的人也看不到本人的封面
        $this->actingAs($b)->post("/members/{$u->id}/block");
        $this->actingAs($b)->get("/covers/{$u->id}")->assertNotFound();
    }

    public function test_photo_review_still_works_with_kind_default(): void
    {
        $u = $this->member('本人');
        $admin = $this->member('管理員', true);
        $this->actingAs($u)->post('/me/photo', ['photo' => UploadedFile::fake()->image('a.jpg', 400, 400)]);
        $p = $u->profile->fresh();
        // 舊的表單沒有帶 kind，也是審大頭照
        $this->actingAs($admin)->post("/admin/photos/{$u->id}", ['decision' => 'approve', 'photo' => $p->photo_path]);
        $this->assertSame('approved', $p->fresh()->photo_status);
        $this->assertSame('', (string) $p->fresh()->cover_status);
    }

    public function test_blocked_cannot_see_cover(): void
    {
        $u = $this->member('本人');
        $b = $this->member('被封鎖的');
        $u->profile->forceFill(['cover_path' => 'c.jpg', 'cover_status' => 'approved'])->save();
        Storage::disk('avatars')->put('c.jpg', 'x');
        $this->actingAs($u)->post("/members/{$b->id}/block");
        $this->actingAs($b)->get("/covers/{$u->id}")->assertNotFound();
    }
}
