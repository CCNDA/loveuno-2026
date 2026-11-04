<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 帳號設定（設計稿 PageMemberSettings：通知 Email、啟用帳號、字體大小、顯示語言）。熊叔叔 10/29「全部都要做」，Asana 1220005922286813（H）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_email')->default(true)->after('points');  // 收到紙條、禮物時寄 email
            $table->boolean('is_active')->default(true)->after('notify_email'); // 「啟用帳號」關掉＝暫停：別人看不到我
            $table->string('font_size', 10)->default('medium')->after('is_active'); // small／medium／large
            $table->string('script', 10)->default('hant')->after('font_size');      // 顯示語言 hant／hans
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_email', 'is_active', 'font_size', 'script']);
        });
    }
};
