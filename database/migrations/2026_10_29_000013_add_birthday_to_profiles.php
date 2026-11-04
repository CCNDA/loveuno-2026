<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 生日（月、日）：只用來提醒好友「生日快到了」，不顯示日期（熊叔叔 2026-10-29「3A」；設計稿 OccasionBlock）。
// 出生年本來就有（birth_year，只顯示年齡層）。Asana 1220005922286727。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('birth_month')->nullable()->after('birth_year');
            $table->unsignedTinyInteger('birth_day')->nullable()->after('birth_month');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['birth_month', 'birth_day']);
        });
    }
};
