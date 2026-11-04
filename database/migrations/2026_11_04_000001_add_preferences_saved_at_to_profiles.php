<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 交友條件「存過」的時間。認識朋友沒存過交友條件才請他先設定（熊叔叔 11/4）；
// 存過、就算四項都選「不限」也算設了 → 列全部（交友條件頁寫「沒填的就是不限」，小霏 11/4 實測抓到原本會卡成迴圈）。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('profiles', 'preferences_saved_at')) {
            return;
        }
        Schema::table('profiles', function (Blueprint $table) {
            $table->timestamp('preferences_saved_at')->nullable()->after('pref_place');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'preferences_saved_at')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('preferences_saved_at');
            });
        }
    }
};
