<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 手機認證到期（舊站 bin/expire_phone_certification.php 也是每天 03:20）
Schedule::command('phone:expire')->dailyAt('03:20');

// 名錄的教會清單（熊叔叔 11/6 決定 #11 甲：名錄每天匯出、愛諾園每天抓一次）；名錄大約晚上更新，清晨抓
Schedule::command('directory:sync-churches')->dailyAt('04:10');
