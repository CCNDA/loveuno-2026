<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // registration.open：註冊沒開放（config loveuno.registration_open）就 404
        $middleware->alias([
            'registration.open' => function ($request, $next) {
                abort_unless(config('loveuno.registration_open'), 404);

                return $next($request);
            },
        ]);

        // 測試站是 Cloudflare → cloudflared → nginx(127.0.0.1) → PHP，要信任本機轉來的 X-Forwarded-Proto，
        // 不然 Laravel 以為是 http，轉址會寫成 http://（小布 10/29 複測發現）。
        // 正式站的代理不同，用 TRUSTED_PROXIES 設定（逗號分隔）。
        $middleware->trustProxies(at: array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')))));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
