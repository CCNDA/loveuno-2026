<?php

namespace App\Http\Middleware;

use App\Support\Points;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// 每日登入 +1（設計稿 PointRecord「每日登入」）。用「記住我」直接進來的人沒有經過登入頁，所以放在這裡：
// 每個 session 一天只查一次資料庫（session 記今天給過了）。
class AwardDailyLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $today = now()->toDateString();
        if ($user && $user->hasVerifiedEmail() && $request->hasSession() && $request->session()->get('points_daily') !== $today) {
            Points::daily($user, 'daily_login');
            $request->session()->put('points_daily', $today);
        }

        return $next($request);
    }
}
