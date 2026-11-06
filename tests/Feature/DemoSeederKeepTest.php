<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 11/6 小薇統籌「新版真實操作測試」：同工帳號（真實信箱）標成 is_demo，重種示範資料不能把它們刪掉
class DemoSeederKeepTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseed_only_deletes_invalid_demo_accounts(): void
    {
        $story = User::factory()->create(['email' => 'vera+lu-story@ccnda.org', 'is_demo' => true]);
        $this->seed(DemoSeeder::class);
        $fake = User::where('email', 'like', '%.invalid')->where('is_demo', true)->count();
        $this->assertGreaterThan(0, $fake);   // 對照：示範會員真的有建

        $this->seed(DemoSeeder::class);       // 重種
        $this->assertNotNull($story->fresh());
        $this->assertSame($fake, User::where('email', 'like', '%.invalid')->where('is_demo', true)->count());
    }
}
