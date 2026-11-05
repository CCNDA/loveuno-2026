<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 愛的見證照片的焦點（臉在哪，左上 0%、右下 100%）：裁成方形或長條時照這裡置中，不會把臉切掉（熊叔叔 2026-11-05）
return new class extends Migration
{
    public function up(): void
    {
        // x、y 各別檢查（小鹿 11/6：只有一欄的半套狀態也要能補齊）
        foreach (['photo_focus_x', 'photo_focus_y'] as $col) {
            if (! Schema::hasColumn('love_stories', $col)) {
                Schema::table('love_stories', function (Blueprint $table) use ($col) {
                    $table->unsignedTinyInteger($col)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['photo_focus_x', 'photo_focus_y'] as $col) {
            if (Schema::hasColumn('love_stories', $col)) {
                Schema::table('love_stories', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
