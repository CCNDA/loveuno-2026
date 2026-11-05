<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 11/5：個人資料頁每個下拉都多了「例如：老師、工程師、學生」——迴圈變數 $hint 漏進 select-field。只能出現在職業欄一次
class ProfileHintLeakTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_hint_only_once(): void
    {
        $u = User::factory()->create();
        Profile::create(["user_id" => $u->id, "gender" => "女", "birth_year" => 1990]);
        $html = $this->actingAs($u)->get(route("profile.edit"))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, "例如：老師、工程師、學生"));
    }
}
