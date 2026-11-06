<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 熊叔叔 11/6 TG「個人檔案年齡是真實年齡，不是範圍」：有生日就算到今天滿幾歲，只有出生年就用今年減出生年
class ProfileAgeTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, array $birth): User
    {
        $u = User::factory()->create(['name' => $name]);
        Profile::create(['user_id' => $u->id, 'gender' => '女'] + $birth);

        return $u;
    }

    public function test_real_age_not_range(): void
    {
        $passed = today()->subYears(34)->subDay();   // 今年生日過了 → 34
        $coming = today()->subYears(34)->addDay();   // 今年生日還沒到 → 33
        $a = $this->member('生日過了', ['birth_year' => $passed->year, 'birth_month' => $passed->month, 'birth_day' => $passed->day]);
        $b = $this->member('生日還沒到', ['birth_year' => $coming->year, 'birth_month' => $coming->month, 'birth_day' => $coming->day]);
        $c = $this->member('只有出生年', ['birth_year' => today()->year - 40]);

        $this->assertSame(34, $a->profile->age());
        $this->assertSame(33, $b->profile->age());
        $this->assertSame(40, $c->profile->age());
        $this->assertSame('34 歲', $a->profile->ageLabel());

        $viewer = $this->member('看的人', ['birth_year' => 1990]);
        $this->actingAs($viewer)->get("/members/{$a->id}")->assertOk()->assertSee('34 歲')->assertDontSee('30～34 歲')
            ->assertDontSee((string) $passed->year); // 只顯示年齡，不顯示出生年
    }
}
