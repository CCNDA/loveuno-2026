<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 刪除「還沒回禮」的禮物＝婉拒，退 3 點給送禮的人（熊叔叔 11/7 TG 按甲）：記下哪一份退過、什麼時候退
// 只加欄位、舊資料不動；可重跑
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gifts', 'refunded_at')) {
            Schema::table('gifts', fn (Blueprint $t) => $t->timestamp('refunded_at')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gifts', 'refunded_at')) {
            Schema::table('gifts', fn (Blueprint $t) => $t->dropColumn('refunded_at'));
        }
    }
};
