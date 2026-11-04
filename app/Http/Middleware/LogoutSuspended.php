<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// 停權的帳號：已經登入的（包括「記住我」）下一個請求就登出。登入頁另外擋（LoginController）。
class LogoutSuspended
{
    public const MESSAGE = '很抱歉，這個帳號已經被停權了。有問題請用「聯絡我們」跟我們聯繫。';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && $user->suspended_at !== null) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
        }

        return $next($request);
    }
}
