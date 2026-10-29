<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name = '會員', array $over = []): User
    {
        $u = User::factory()->create(['name' => $name] + $over);
        Profile::create(['user_id' => $u->id, 'gender' => '女', 'birth_year' => 1990]);

        return $u;
    }

    public function test_report_link_and_form_follow_design(): void
    {
        $me = $this->member('我');
        $bad = $this->member('可疑的人');
        $this->actingAs($me)->get("/members/{$bad->id}")->assertSee(route('reports.create', $bad), false);
        $this->actingAs($me)->get("/members/{$me->id}")->assertDontSee('檢舉這位會員');

        $this->actingAs($me)->get("/members/{$bad->id}/report")->assertOk()
            ->assertSee('檢舉會員的不當行為')->assertSee('愛諾園是一個屬神的交友空間')
            ->assertSeeInOrder(['請選擇檢舉原因', '詐騙', '廣告', '暴力', '仇恨言論', '其他']);
        $this->actingAs($me)->get("/members/{$me->id}/report")->assertNotFound();
    }

    public function test_submit_report_with_screenshot(): void
    {
        Storage::fake('avatars');
        $me = $this->member('我');
        $bad = $this->member('可疑的人');

        $this->actingAs($me)->post("/members/{$bad->id}/report", [
            'reason' => 'fraud', 'description' => '一直要我匯款', 'screenshot' => UploadedFile::fake()->image('s.png', 800, 1600),
        ])->assertRedirect('/members')->assertSessionHas('status', fn ($s) => str_contains($s, '保密'));

        $r = Report::firstOrFail();
        $this->assertSame([$me->id, $bad->id, 'fraud', '一直要我匯款', 'open'], [$r->reporter_id, $r->reported_id, $r->reason, $r->description, $r->status]);
        Storage::disk('avatars')->assertExists($r->screenshot_path);
        $this->assertStringStartsWith('reports/', $r->screenshot_path);
    }

    public function test_validation_messages(): void
    {
        $me = $this->member('我');
        $bad = $this->member('可疑的人');
        $this->actingAs($me)->post("/members/{$bad->id}/report", ['reason' => '', 'description' => ''])
            ->assertSessionHasErrors(['reason' => '請選擇檢舉原因。', 'description']);
        $this->actingAs($me)->post("/members/{$bad->id}/report", ['reason' => 'hack', 'description' => str_repeat('字', 501)])
            ->assertSessionHasErrors(['reason', 'description' => '內容描述不能超過 500 字。']);
        $this->actingAs($me)->post("/members/{$bad->id}/report", ['reason' => 'ad', 'description' => 'x', 'screenshot' => UploadedFile::fake()->create('a.webp', 10, 'image/webp')])
            ->assertSessionHasErrors('screenshot');
        $this->assertSame(0, Report::count());
    }

    public function test_can_report_even_when_blocked_either_way(): void
    {
        $me = $this->member('我');
        $bad = $this->member('可疑的人');
        DB::table('blocks')->insert(['blocker_id' => $bad->id, 'blocked_id' => $me->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($me)->get("/members/{$bad->id}/report")->assertOk();

        // 換成只有我封鎖對方：檔案頁「解除封鎖」下面有檢舉連結（對方封鎖我時，檔案頁是 404，只能從網址或封鎖前開的頁面進來）
        DB::table('blocks')->delete();
        DB::table('blocks')->insert(['blocker_id' => $me->id, 'blocked_id' => $bad->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($me)->get("/members/{$bad->id}")->assertSee(route('reports.create', $bad), false);
        $this->actingAs($me)->post("/members/{$bad->id}/report", ['reason' => 'other', 'description' => '騷擾'])->assertRedirect('/members');
        $this->assertSame(1, Report::count());
    }

    public function test_admin_sees_and_closes_reports_others_cannot(): void
    {
        Storage::fake('avatars');
        $me = $this->member('檢舉人');
        $bad = $this->member('被檢舉人');
        $admin = $this->member('管理員', ['is_admin' => true]);
        $this->actingAs($me)->post("/members/{$bad->id}/report", ['reason' => 'ad', 'description' => '<script>x</script>賣保健食品', 'screenshot' => UploadedFile::fake()->image('s.jpg', 600, 600)]);
        $r = Report::firstOrFail();

        $this->actingAs($me)->get('/admin/reports')->assertForbidden();
        $this->actingAs($me)->get("/admin/reports/{$r->id}/screenshot")->assertForbidden();
        $this->actingAs($bad)->get('/')->assertDontSee('檢舉（');

        $this->actingAs($admin)->get('/')->assertSee('檢舉（1');
        $this->actingAs($admin)->get('/admin/reports')->assertOk()
            ->assertSee('第 ' . $r->id . ' 號：廣告')->assertSee('被檢舉人')->assertSee('檢舉人')
            ->assertSee('&lt;script&gt;x&lt;/script&gt;賣保健食品', false);
        $this->actingAs($admin)->get("/admin/reports/{$r->id}/screenshot")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($admin)->post("/admin/reports/{$r->id}/close", ['admin_note' => ''])->assertSessionHasErrors('admin_note');
        $this->actingAs($admin)->post("/admin/reports/{$r->id}/close", ['admin_note' => '已警告'])->assertRedirect('/admin/reports');
        $r->refresh();
        $this->assertSame(['closed', '已警告', $admin->id], [$r->status, $r->admin_note, $r->handled_by]);
        $this->actingAs($admin)->post("/admin/reports/{$r->id}/close", ['admin_note' => '再一次'])->assertStatus(409);
        $this->actingAs($admin)->get('/admin/reports')->assertSee('已警告');
    }

    public function test_report_survives_account_deletion(): void
    {
        $me = $this->member('檢舉人');
        $bad = $this->member('被檢舉人');
        $this->actingAs($me)->post("/members/{$bad->id}/report", ['reason' => 'violence', 'description' => '威脅']);
        $bad->delete();
        $r = Report::firstOrFail();
        $this->assertNull($r->reported_id);
        $admin = $this->member('管理員', ['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/reports')->assertSee('（帳號已刪除）');
    }

    public function test_report_is_rate_limited(): void
    {
        $me = $this->member('我');
        $bad = $this->member('可疑的人');
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($me)->post("/members/{$bad->id}/report", ['reason' => 'other', 'description' => "第{$i}次"]);
        }
        $this->actingAs($me)->post("/members/{$bad->id}/report", ['reason' => 'other', 'description' => 'x'])->assertStatus(429);
    }
}
