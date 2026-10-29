<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 送禮物（設計稿 SendGiftDialog；舊站 gift 表）。互送禮物就成為「普通好友」（熊叔叔 10/29「1A」）。
// Asana 1220006001889118。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('item');       // App\Models\Gift::ITEMS（設計稿 12 種）
            $table->string('message', 20)->default(''); // 「20個中文字以內的簡訊」
            $table->date('sent_on');                    // 「每天只可送一份禮物給同一個會員」
            $table->timestamp('opened_at')->nullable(); // 收禮的人看過了沒
            $table->timestamps();
            $table->unique(['sender_id', 'recipient_id', 'sent_on']);
            $table->index(['recipient_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gifts');
    }
};
