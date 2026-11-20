<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 公測邀請名單（熊叔叔 11/16 選乙；Asana 1220564012971609）：有值＝在名單上、什麼時候加的
// 只加欄位、舊資料不動；可重跑
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'beta_invited_at')) {
            Schema::table('users', fn (Blueprint $t) => $t->timestamp('beta_invited_at')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'beta_invited_at')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('beta_invited_at'));
        }
    }
};
