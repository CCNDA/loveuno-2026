<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// 公測停寫開關（熊叔叔 11/17 TG 選甲；Asana 1220586286066431）：
// .env BETA_READONLY=1 → 所有會改資料的請求（POST／PUT／PATCH／DELETE）都不做，回「公測暫停中」；看的頁面照常。
// 只放行登入、登出（不放行的話，已登入的人登不出去、管理員也進不來看狀況）。
// 生效：改 .env 後 php artisan config:clear（有做 config:cache 的話改跑 config:cache）。
class BetaReadonly
{
    public const MESSAGE = '新版愛諾園公測暫停中，現在只能瀏覽、不能新增或修改。舊版網站照常可以使用，造成不便請見諒！';

    private const ALLOW = ['login.store', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('loveuno.beta_readonly') || $request->isMethodSafe()) {
            return $next($request);
        }
        if (in_array($request->route()?->getName(), self::ALLOW, true)) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], 503);
        }

        return redirect()->back(fallback: route('home'))->withErrors(['beta_readonly' => self::MESSAGE])->with('status', self::MESSAGE);
    }
}
