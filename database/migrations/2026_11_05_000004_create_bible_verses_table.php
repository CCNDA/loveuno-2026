<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 聖經經文（和合本，舊站 bible/cniv.sql）：最愛經文選了書卷章節就自動帶出（熊叔叔 2026-11-05）。內容由 php artisan bible:import 匯入
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bible_verses')) {
            return;
        }
        Schema::create('bible_verses', function (Blueprint $table) {
            $table->unsignedTinyInteger('book');      // 1～66，順序同 ProfileOptions::BIBLE_BOOKS
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse');
            $table->text('text');
            $table->primary(['book', 'chapter', 'verse']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_verses');
    }
};
