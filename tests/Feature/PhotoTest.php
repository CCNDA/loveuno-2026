<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('avatars');
    }

    private function member(string $name = '會員', bool $admin = false): User
    {
        $u = User::factory()->create(['name' => $name]);
        $u->forceFill(['is_admin' => $admin])->save();
        Profile::create(['user_id' => $u->id, 'gender' => '女']);

        return $u->fresh();
    }

    public function test_guest_cannot_upload_or_view(): void
    {
        $u = $this->member();
        $this->get('/me/photo')->assertRedirect('/login');
        $this->get("/photos/{$u->id}")->assertRedirect('/login');
    }

    public function test_upload_is_reencoded_and_pending(): void
    {
        $u = $this->member();
        $file = UploadedFile::fake()->image('me.png', 800, 600);

        $this->actingAs($u)->post('/me/photo', ['photo' => $file])->assertRedirect('/me/photo');

        $p = $u->profile->fresh();
        $this->assertSame('pending', $p->photo_status);
        Storage::disk('avatars')->assertExists($p->photo_path);
        $info = getimagesizefromstring(Storage::disk('avatars')->get($p->photo_path));
        $this->assertSame([600, 600, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]); // 一律重做成 600×600 JPEG
        $this->assertStringEndsWith('.jpg', $p->photo_path);
        $this->assertStringNotContainsString('me', $p->photo_path); // 檔名是亂數，不沿用原檔名
    }

    public function test_fake_image_and_small_image_are_rejected(): void
    {
        $u = $this->member();
        $fake = UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo 1;');
        $this->actingAs($u)->post('/me/photo', ['photo' => $fake])->assertSessionHasErrors('photo');
        $small = UploadedFile::fake()->image('tiny.jpg', 100, 100);
        $this->actingAs($u)->post('/me/photo', ['photo' => $small])->assertSessionHasErrors('photo');
        $big = UploadedFile::fake()->image('big.jpg', 800, 800)->size(6000);
        $this->actingAs($u)->post('/me/photo', ['photo' => $big])->assertSessionHasErrors('photo');
        $this->assertNull($u->profile->fresh()->photo_path);
    }

    public function test_pending_photo_only_owner_and_admin_can_see(): void
    {
        $owner = $this->member('本人');
        $other = $this->member('別人');
        $admin = $this->member('管理員', true);
        $this->actingAs($owner)->post('/me/photo', ['photo' => UploadedFile::fake()->image('a.jpg', 400, 400)]);

        $this->actingAs($owner)->get("/photos/{$owner->id}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($admin)->get("/photos/{$owner->id}")->assertOk();
        $this->actingAs($other)->get("/photos/{$owner->id}")->assertNotFound();
        // 列表上別人看到的還是文字頭像
        $this->actingAs($other)->get('/members')->assertDontSee("/photos/{$owner->id}", false);
    }

    public function test_admin_approves_then_everyone_logged_in_sees(): void
    {
        $owner = $this->member('本人');
        $other = $this->member('別人');
        $admin = $this->member('管理員', true);
        $this->actingAs($owner)->post('/me/photo', ['photo' => UploadedFile::fake()->image('a.jpg', 400, 400)]);
        $p = $owner->profile->fresh();

        $this->actingAs($other)->get('/admin/photos')->assertForbidden();
        $this->actingAs($other)->post("/admin/photos/{$p->user_id}", ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($admin)->get('/admin/photos')->assertOk()->assertSee('本人');
        $this->actingAs($admin)->post("/admin/photos/{$p->user_id}", ['decision' => 'approve'])->assertRedirect('/admin/photos');

        $this->assertSame('approved', $p->fresh()->photo_status);
        $this->actingAs($other)->get("/photos/{$owner->id}")->assertOk();
        $this->actingAs($other)->get('/members')->assertSee("/photos/{$owner->id}", false);
    }

    public function test_reject_deletes_file(): void
    {
        $owner = $this->member('本人');
        $admin = $this->member('管理員', true);
        $this->actingAs($owner)->post('/me/photo', ['photo' => UploadedFile::fake()->image('a.jpg', 400, 400)]);
        $path = $owner->profile->fresh()->photo_path;

        $this->actingAs($admin)->post("/admin/photos/{$owner->id}", ['decision' => 'reject']);

        Storage::disk('avatars')->assertMissing($path);
        $this->assertSame('rejected', $owner->profile->fresh()->photo_status);
        $this->actingAs($owner)->get("/photos/{$owner->id}")->assertNotFound();
    }

    public function test_replace_and_delete_remove_old_files(): void
    {
        $u = $this->member();
        $this->actingAs($u)->post('/me/photo', ['photo' => UploadedFile::fake()->image('a.jpg', 400, 400)]);
        $first = $u->profile->fresh()->photo_path;
        $this->actingAs($u)->post('/me/photo', ['photo' => UploadedFile::fake()->image('b.jpg', 400, 400)]);
        $second = $u->profile->fresh()->photo_path;

        Storage::disk('avatars')->assertMissing($first);
        Storage::disk('avatars')->assertExists($second);
        $this->assertSame('pending', $u->profile->fresh()->photo_status); // 換照片要重新審核

        $this->actingAs($u)->delete('/me/photo')->assertRedirect('/me/photo');
        Storage::disk('avatars')->assertMissing($second);
        $this->assertNull($u->profile->fresh()->photo_path);
    }

    public function test_set_admin_command(): void
    {
        $u = $this->member();
        $this->artisan('user:admin', ['email' => strtoupper($u->email)])->assertSuccessful();
        $this->assertTrue($u->fresh()->is_admin);
        $this->artisan('user:admin', ['email' => $u->email, '--off' => true])->assertSuccessful();
        $this->assertFalse($u->fresh()->is_admin);
        $this->artisan('user:admin', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
