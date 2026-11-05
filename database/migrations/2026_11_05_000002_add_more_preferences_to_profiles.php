<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 交友條件補齊設計稿的欄位（熊叔叔 2026-11-05 TG「交友條件設定少了很多項」）：婚姻狀態、信仰、身高、體重。空字串／null＝不拘
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('pref_marital', 10)->default('')->after('pref_place');
            $table->string('pref_faith', 10)->default('')->after('pref_marital');
            $table->unsignedSmallInteger('pref_height_min')->nullable()->after('pref_faith');
            $table->unsignedSmallInteger('pref_height_max')->nullable()->after('pref_height_min');
            $table->unsignedSmallInteger('pref_weight_min')->nullable()->after('pref_height_max');
            $table->unsignedSmallInteger('pref_weight_max')->nullable()->after('pref_weight_min');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['pref_marital', 'pref_faith', 'pref_height_min', 'pref_height_max', 'pref_weight_min', 'pref_weight_max']);
        });
    }
};
