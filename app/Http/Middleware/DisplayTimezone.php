<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// 時區自動偵測（熊叔叔 10/29「直接偵測」）：public/js/tz.js 把瀏覽器的時區寫進 cookie「tz」，這裡讀出來，
// 畫面上顯示幾點幾分的地方用 ->shown() 換成會員所在的時區。資料庫照舊存台北時間（app.timezone），只有顯示換。
class DisplayTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $tz = (string) $request->cookie('tz', '');
        if ($tz === '' || ! in_array($tz, timezone_identifiers_list(), true)) {
            $tz = (string) config('app.timezone');
        }
        app()->instance('display_tz', $tz);

        return $next($request);
    }
}
