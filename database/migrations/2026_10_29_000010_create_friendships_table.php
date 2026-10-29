<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 好友關係（設計稿 PagePersonal「與您關係」「解除關係」；舊站 relationship 表的 1 普通好友／2 聊天好友／3 真實好友）。
// 熊叔叔 2026-10-29「全部都要做」，Asana 1220005922286727。
// 一對會員只存一列：user_id 放比較小的 id、friend_id 放比較大的，unique 擋重複。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');      // 1 普通好友、2 聊天好友、3 真實好友
            $table->timestamp('leveled_at')->nullable(); // 升到目前這一級的時間
            $table->timestamps();                      // created_at＝成為好友的那天（好友週年用）
            $table->unique(['user_id', 'friend_id']);
            $table->index('friend_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
