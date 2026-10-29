<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 日記的代禱、「為你禱告」、留言、附圖（設計稿首頁 DiaryWall；熊叔叔 2026-10-29「新版禱告功能也有」「全部都要做」，
// Asana 1220006070568670）
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diaries', function (Blueprint $table) {
            $table->boolean('is_prayer')->default(false)->after('visibility'); // 作者標成「代禱」
            $table->string('image_path', 100)->nullable()->after('is_prayer');  // 在 avatars 磁碟裡的路徑（亂數檔名）
            $table->string('image_status', 10)->default('')->after('image_path'); // 0.14.0 原本要審核；0.15.1 起不用審核（熊叔叔 10/29「1B」），上傳就是 approved。欄位留著，以後要改回審核不用再改表
            $table->timestamp('image_updated_at')->nullable()->after('image_status');
            $table->index('image_status');
        });

        // 一個人對一篇日記只算一次「為你禱告」，再按一次是取消
        Schema::create('diary_prayers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['diary_id', 'user_id']);
        });

        Schema::create('diary_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            $table->timestamps();
            $table->index(['diary_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_comments');
        Schema::dropIfExists('diary_prayers');
        Schema::table('diaries', function (Blueprint $table) {
            $table->dropIndex(['image_status']);
            $table->dropColumn(['is_prayer', 'image_path', 'image_status', 'image_updated_at']);
        });
    }
};
