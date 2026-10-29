<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 大頭照上傳＋審核（熊叔叔 2026-10-29 TG 選 B：11/8 前做照片上傳，Asana 1215470856990770）
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('photo_path', 100)->nullable()->after('hope');   // 在 avatars 磁碟裡的路徑（亂數檔名）
            $table->string('photo_status', 10)->default('')->after('photo_path'); // ''／pending／approved／rejected
            $table->timestamp('photo_updated_at')->nullable()->after('photo_status');
            $table->index('photo_status');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('is_demo');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['photo_status']);
            $table->dropColumn(['photo_path', 'photo_status', 'photo_updated_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
