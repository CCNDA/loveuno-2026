<?php

namespace App\Http\Middleware;

use App\Support\ToSimplified;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// 使用者選了「简体」：把要送出的 HTML 轉成簡體，<html lang> 改成 zh-Hans。
// 只動 text/html；圖片、JSON、轉址都不碰。選擇存在 session（script=hans／hant），預設繁體。
class ConvertScript
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->session()->get('script') !== 'hans') {
            return $response;
        }
        if (! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }
        $body = $response->getContent();
        if (! is_string($body) || $body === '') {
            return $response;
        }

        $body = ToSimplified::html($body);
        $body = str_replace('<html lang="zh-Hant-TW">', '<html lang="zh-Hans">', $body);
        $response->setContent($body);

        return $response;
    }
}
