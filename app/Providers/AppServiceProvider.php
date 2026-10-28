<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 頁尾版本號（熊叔叔 10/23 通則：每站都要有版本資訊），來源是根目錄 VERSION
        $file = base_path('VERSION');
        View::share('appVersion', is_readable($file) ? trim((string) file_get_contents($file)) : 'dev');
    }
}
