<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TestLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TestLoginLinkTest extends TestCase
{
    use RefreshDatabase;

    private function enable(array $users): void
    {
        config(['loveuno.test_login_links' => true, 'loveuno.test_login_link_users' => $users]);
        // 路徑是開機時依設定註冊的：測試裡開了開關要重新載一次路由
        if (! Route::has('test.login')) {
            Route::middleware('web')->group(base_path('routes/web.php'));
            app('router')->getRoutes()->refreshNameLookups();
        }
    }

    private function sendAndGetUrl(User $u): string
    {
        Notification::fake();
        $this->artisan('test:login-link', ['user' => $u->id])->assertSuccessful();
        $url = null;
        Notification::assertSentOnDemand(TestLoginLink::class, function ($n, $channels, $notifiable) use (&$url, $u) {
            $url = $n->toMail($notifiable)->actionUrl;

            return $notifiable->routes['mail'] === $u->email;
        });

        return $url;
    }

    public function test_link_logs_in_once(): void
    {
        $u = User::factory()->create();
        $this->enable([$u->id]);
        $url = $this->sendAndGetUrl($u);
        $this->get($url)->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
        auth()->logout();
        $this->get($url)->assertStatus(410);
        $this->assertGuest();
    }

    public function test_tampered_or_expired_link_rejected(): void
    {
        $u = User::factory()->create();
        $this->enable([$u->id]);
        $url = $this->sendAndGetUrl($u);
        $this->get(preg_replace('/signature=[^&]+/', 'signature=' . str_repeat('0', 64), $url))->assertStatus(403);
        $this->travel(16)->minutes();
        $this->get($url)->assertStatus(403);
        $this->assertGuest();
    }

    public function test_only_listed_users(): void
    {
        $listed = User::factory()->create();
        $other = User::factory()->create();
        $this->enable([$listed->id]);
        $this->artisan('test:login-link', ['user' => $other->id])->assertFailed();
    }

    public function test_switched_off_means_no_route_and_no_command(): void
    {
        $u = User::factory()->create();
        config(['loveuno.test_login_links' => false, 'loveuno.test_login_link_users' => [$u->id]]);
        $this->artisan('test:login-link', ['user' => $u->id])->assertFailed();
        $this->get("/test-login/{$u->id}/" . str_repeat('a', 40))->assertNotFound();
    }
}
