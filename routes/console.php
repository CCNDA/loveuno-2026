<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 手機認證到期（舊站 bin/expire_phone_certification.php 也是每天 03:20）
Schedule::command('phone:expire')->dailyAt('03:20');
