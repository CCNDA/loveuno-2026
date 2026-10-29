<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 交友條件（loveuno-web 版型的會員中心「交友條件」分頁；舊站 profile.hope_sex／hope_age1／hope_age2／hope_live_place）
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('pref_gender', 10)->default('')->after('hope');        // 男／女／''（不限）
            $table->unsignedTinyInteger('pref_age_min')->nullable()->after('pref_gender');
            $table->unsignedTinyInteger('pref_age_max')->nullable()->after('pref_age_min');
            $table->string('pref_place', 20)->default('')->after('pref_age_max'); // ''＝不限
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['pref_gender', 'pref_age_min', 'pref_age_max', 'pref_place']);
        });
    }
};
