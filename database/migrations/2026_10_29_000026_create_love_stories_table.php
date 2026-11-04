<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 愛的見證（設計稿 PageNewLoveStory 投稿、PageViewLoveStory 內頁、PageWelcome 首頁卡片）。
// 會員投稿 → 管理員審核通過才公開。Asana 1220005817038338（I）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('love_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submitter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('male_id')->nullable()->constrained('users')->nullOnDelete();   // 註銷了就變空的，見證還在
            $table->foreignId('female_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 50);
            $table->text('body');                         // 1000 字以內（設計稿 maxLength）
            $table->string('photo_path')->nullable();     // avatars 磁碟（私有），經過 LoveStoryController::photo 送出
            $table->string('status', 10)->default('pending'); // pending／approved／rejected
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('love_stories');
    }
};
