<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 個人相簿（設計稿 PageMemberProfile PersonalAlbum「相簿照片(上限為9張)」、PagePersonalAlbum）。
// 跟大頭照一樣要管理員審核才給別人看。Asana 1220005816992951（E 第二部分）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('album_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path', 100);                    // 在 avatars 磁碟（私有）裡的路徑
            $table->string('status', 10)->default('pending'); // pending／approved
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_photos');
    }
};
