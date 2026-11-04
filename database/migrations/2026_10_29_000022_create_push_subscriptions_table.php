<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Web Push 訂閱（每台裝置一筆）。熊叔叔 10/29「Web app 加到主畫面可以做」，Asana 1220005922286813（H）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique(); // endpoint 很長，用 sha256 擋重複
            $table->string('p256dh', 200);
            $table->string('auth', 100);
            $table->string('user_agent', 200)->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
