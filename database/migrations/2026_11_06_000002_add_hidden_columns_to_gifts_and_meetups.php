<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 查看禮物、約會邀請改兩欄清單（熊叔叔 11/6 決定 #9）：清單可以勾選刪除，只是自己這邊看不到，對方還在
// 只加欄位、舊資料不動；每一欄各自檢查，可重跑
return new class extends Migration
{
    public function up(): void
    {
        foreach (['gifts', 'meetups'] as $table) {
            foreach (['sender_deleted_at', 'recipient_deleted_at'] as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    Schema::table($table, fn (Blueprint $t) => $t->timestamp($col)->nullable());
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['gifts', 'meetups'] as $table) {
            foreach (['sender_deleted_at', 'recipient_deleted_at'] as $col) {
                if (Schema::hasColumn($table, $col)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($col));
                }
            }
        }
    }
};
