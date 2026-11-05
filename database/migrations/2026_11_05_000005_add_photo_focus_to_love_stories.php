<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 愛的見證照片的焦點（臉在哪，左上 0%、右下 100%）：裁成方形或長條時照這裡置中，不會把臉切掉（熊叔叔 2026-11-05）
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('love_stories', 'photo_focus_x')) {
            return;
        }
        Schema::table('love_stories', function (Blueprint $table) {
            $table->unsignedTinyInteger('photo_focus_x')->nullable();
            $table->unsignedTinyInteger('photo_focus_y')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('love_stories', 'photo_focus_x')) {
            Schema::table('love_stories', function (Blueprint $table) {
                $table->dropColumn(['photo_focus_x', 'photo_focus_y']);
            });
        }
    }
};
