<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 日記留言內容改 text：舊站回覆沒有字數上限，搬過來不能截斷（審查 10/30）。新版自己寫的留言還是限 500 字（表單驗證）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_comments', function (Blueprint $table) {
            $table->text('body')->change();
        });
    }

    public function down(): void
    {
        // 改回 500 之前，超過的會被資料庫擋下：只有在沒有搬舊站資料時才退得回去
        Schema::table('diary_comments', function (Blueprint $table) {
            $table->string('body', 500)->change();
        });
    }
};
