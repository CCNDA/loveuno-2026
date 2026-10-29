<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 約會（約見面邀請）：照舊站 member/private_date*.php（資料表 private_date），欄位文字照設計稿 AskOutDialog。
// Asana 1220005805400335（J）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->date('meet_on');
            $table->string('meet_time', 5);     // HH:MM（24 小時制，每 30 分鐘一格）
            $table->string('place', 100);
            $table->string('activity', 20);     // App\Models\Meetup::ACTIVITIES
            $table->string('company', 20);      // App\Models\Meetup::COMPANY
            $table->string('message', 1000)->nullable();
            // pending 等回覆／accepted 準備參加／declined 婉拒／cancelled 取消／attended 已參加／absent 沒參加
            $table->string('status', 10)->default('pending');
            $table->string('reply', 500)->nullable();       // 接受、婉拒時寫的話
            $table->string('closing_note', 500)->nullable(); // 取消原因、約會感言、沒參加的原因
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_id', 'status']);
            $table->index(['sender_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetups');
    }
};
