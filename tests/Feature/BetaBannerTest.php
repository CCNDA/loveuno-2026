<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 公測橫幅（熊叔叔 11/23 TG 選甲）：全站匯入後是真資料，不能再說「展示版、示範資料」
class BetaBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_says_beta_and_links_feedback(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('公測站')->assertSee('資料跟舊站同步')
            ->assertDontSee('示範資料')
            ->assertSee(route('feedback.create', ['from' => '/login']), false);
    }
}
