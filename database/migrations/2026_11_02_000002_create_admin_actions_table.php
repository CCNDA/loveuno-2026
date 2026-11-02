<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 後台對會員做的每一件事都留紀錄：誰、什麼時候、對誰、做了什麼（停權、改婚姻、加減點、改暱稱）
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users');
            $table->foreignId('user_id')->constrained('users');
            $table->string('action', 30);   // suspend／unsuspend／marital／points／rename
            $table->json('detail')->nullable(); // 改之前、改之後、原因
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
