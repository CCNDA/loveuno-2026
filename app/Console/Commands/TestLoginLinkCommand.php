<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TestLoginLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

// 測試站：寄一條限時一次性登入連結給名單上的測試會員（連結不印在畫面上，只寄到他的信箱）
class TestLoginLinkCommand extends Command
{
    protected $signature = 'test:login-link {user : 會員編號（要在 TEST_LOGIN_LINK_USERS 名單上）} {--minutes=15 : 幾分鐘內有效（1～60）}';

    protected $description = '測試站：寄限時一次性登入連結（只在 TEST_LOGIN_LINKS=true、非 production）';

    public function handle(): int
    {
        if (! config('loveuno.test_login_links') || app()->isProduction()) {
            $this->error('這個站沒有開 TEST_LOGIN_LINKS（或是正式站），不能用。');

            return self::FAILURE;
        }
        $id = (int) $this->argument('user');
        if (! in_array($id, config('loveuno.test_login_link_users'), true)) {
            $this->error("#{$id} 不在 TEST_LOGIN_LINK_USERS 名單上。");

            return self::FAILURE;
        }
        $user = User::query()->whereKey($id)->whereNull('deleted_at')->first();
        if (! $user) {
            $this->error("找不到 #{$id}。");

            return self::FAILURE;
        }
        $minutes = max(1, min(60, (int) $this->option('minutes')));
        $nonce = Str::random(40);
        Cache::put("test-login:{$id}:{$nonce}", true, now()->addMinutes($minutes));
        $url = URL::temporarySignedRoute('test.login', now()->addMinutes($minutes), ['user' => $id, 'nonce' => $nonce]);
        Notification::route('mail', $user->email)->notify(new TestLoginLink($url, $minutes));
        $this->info("已寄出給 #{$id}（{$minutes} 分鐘內有效、只能用一次）。");

        return self::SUCCESS;
    }
}
