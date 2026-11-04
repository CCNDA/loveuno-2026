<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 站務公告（設計稿 PageInformation：公告標題、發佈時間、內文）。管理員新增、修改、刪除。Asana 1220005817038338（I）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('body');                       // 純文字，畫面上換行照原樣
            $table->timestamp('published_at')->nullable()->index(); // 空的＝草稿；未來的時間＝到時候才出現
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
