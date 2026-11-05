<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 個人化封面 10 選 1（設計稿 個人相簿「個人化封面」；熊叔叔 2026-11-05）。null＝沒選，顯示第 1 張
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('profiles', 'cover_preset')) {
            return;
        }
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('cover_preset')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'cover_preset')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('cover_preset');
            });
        }
    }
};
