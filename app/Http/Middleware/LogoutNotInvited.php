<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// 公測邀請制（熊叔叔 11/16 選乙「邀請名單」；Asana 1220564012971609）：
// config loveuno.beta_invite_only 開著時，只有名單上的人（users.beta_invited_at 有值）和管理員能用新版。
// 已經登入的（包括「記住我」）下一個請求就登出；登入頁另外擋（LoginController）。
class LogoutNotInvited
{
    public const MESSAGE = '新版愛諾園目前是邀請制公測，這個帳號還不在這次的名單裡。舊版網站照常可以使用，謝謝你的耐心！';

    public static function blocks(?\App\Models\User $user): bool
    {
        return $user !== null
            && config('loveuno.beta_invite_only')
            && ! $user->is_admin
            && $user->beta_invited_at === null;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (self::blocks($request->user())) {
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
