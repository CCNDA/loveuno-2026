<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

// 測試站限時一次性登入連結（config loveuno.test_login_links；大蘇 11/21）
// 網址要有簽章（signed）、沒過期；nonce 用 Cache::pull 取一次就沒了 → 只能用一次
class TestLoginLinkController extends Controller
{
    public function __invoke(Request $request, int $user, string $nonce)
    {
        abort_unless(config('loveuno.test_login_links') && ! app()->isProduction(), 404);
        abort_unless(in_array($user, config('loveuno.test_login_link_users'), true), 404);
        if (Cache::pull("test-login:{$user}:{$nonce}") !== true) {
            abort(410, '這個登入連結已經用過或過期了，請再要一條新的。');
        }
        $member = User::query()->whereKey($user)->whereNull('deleted_at')->firstOrFail();
        Auth::login($member);
        $request->session()->regenerate();

        return redirect('/');
    }
}
