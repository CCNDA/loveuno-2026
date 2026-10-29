<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 愛諾積點（設計稿 PageMemberPoints：任務獎勵、使用紀錄；舊站 coin 表＋basic.coin）。
// 熊叔叔 2026-10-29「全部都要做」，Asana 1220027072582776。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('points')->default(0)->after('is_admin'); // 目前餘額＝point_transactions 加總
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);   // App\Support\Points 的 key
            $table->string('ref', 40);    // 同一種只算一次的依據：once、日期、禮物編號…
            $table->integer('amount');    // 加點是正的、花點是負的
            $table->string('label', 100); // 畫面上的「項目」
            $table->timestamps();
            $table->unique(['user_id', 'type', 'ref']); // 擋重複給點（連按、兩個請求同時進來）
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('points');
        });
    }
};
