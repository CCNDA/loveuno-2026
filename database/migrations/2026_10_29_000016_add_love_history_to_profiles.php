<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 戀愛履歷問卷（熊叔叔 2026-10-29「3A」：照舊站 member/profile3.php）。答案存成一個 JSON，key 沿用舊站欄位名（cvcurrent…）。
// Asana 1220005816992951（E）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->json('love_history')->nullable()->after('sports');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('love_history');
        });
    }
};
