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

// 公測寫入紀錄只留 BETA_AUDIT_KEEP_DAYS 天（預設 60；裡面有 email、手機這類個資。小布 11/21 審查 🟡6）
Artisan::command('beta:prune-audits', function () {
    $days = max(1, (int) config('loveuno.beta_audit_keep_days'));
    $n = \Illuminate\Support\Facades\DB::table('beta_audits')->where('created_at', '<', now()->subDays($days))->delete();
    $this->info("刪掉 {$n} 筆超過 {$days} 天的公測寫入紀錄");
})->purpose('公測寫入紀錄 beta_audits 只留 N 天');
Schedule::command('beta:prune-audits')->dailyAt('03:40');
