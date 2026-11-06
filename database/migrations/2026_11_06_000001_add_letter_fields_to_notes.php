<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 傳紙條改「情書」樣式（熊叔叔 11/6 決定 #8）：每封有標題；清單可以勾選刪除（只是自己這邊看不到，對方還在）
// 只加欄位、舊資料不動（沒有標題的舊紙條，畫面上用內文前 20 字當標題）；每一欄各自檢查，可重跑
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'title' => fn (Blueprint $t) => $t->string('title', 20)->nullable()->after('recipient_id'),
            'sender_deleted_at' => fn (Blueprint $t) => $t->timestamp('sender_deleted_at')->nullable(),
            'recipient_deleted_at' => fn (Blueprint $t) => $t->timestamp('recipient_deleted_at')->nullable(),
        ] as $col => $add) {
            if (! Schema::hasColumn('notes', $col)) {
                Schema::table('notes', $add);
            }
        }
    }

    public function down(): void
    {
        foreach (['title', 'sender_deleted_at', 'recipient_deleted_at'] as $col) {
            if (Schema::hasColumn('notes', $col)) {
                Schema::table('notes', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
