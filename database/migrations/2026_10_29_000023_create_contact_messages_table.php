<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 聯絡我們（設計稿 PageContactUs；舊站 info/contact.php）。Asana 1220005817038338（I）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // 有登入才有
            $table->string('name', 30);
            $table->string('email', 100);
            $table->string('subject', 20);   // App\Models\ContactMessage::SUBJECTS
            $table->string('body', 1000);
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['handled_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
