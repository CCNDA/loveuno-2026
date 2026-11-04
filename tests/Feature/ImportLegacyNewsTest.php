<?php

namespace Tests\Feature;

use App\Console\Commands\ImportLegacyNews;
use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportLegacyNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_converts_html_and_imports_once(): void
    {
        $this->assertSame("活動詳情請按這裡\n報導：國度復興報（http://www.krtnews.com.tw/a）", ImportLegacyNews::toText('活動詳情請按<a href=?xmas=index>這裡</a> <br><a href=?x><img src="/a.jpg"></a>報導：<a href="http://www.krtnews.com.tw/a" target=_blank>國度復興報</a>'));
        $this->assertSame('網站正在進行改版規劃中，請為著改版順利代禱。', ImportLegacyNews::titleOf("網站正在進行改版規劃中，請為著改版順利代禱。第二句"));

        $this->artisan('legacy:import-news')->assertSuccessful();
        $n = Announcement::count();
        $this->assertGreaterThan(20, $n);
        $this->assertSame(3, Announcement::published()->count()); // 舊站首頁只列 status 1
        $this->artisan('legacy:import-news')->expectsOutputToContain('已經有的 ' . $n . ' 則沒動')->assertSuccessful();
        $this->assertSame($n, Announcement::count());
        $this->assertSame(0, Announcement::where('body', 'like', '%<%')->count());
        $this->get('/news')->assertSee('東方閃電');
    }
}
