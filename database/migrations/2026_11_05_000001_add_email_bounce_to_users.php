<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 退信（熊叔叔 11/5 TG「遇到信件無效是不是可以讓她重新驗證信箱或是更改信箱」）：
// 信箱寄不到（硬退）時記下時間和原因，停寄通知信；會員重新驗證或改信箱後清掉。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'email_bounced_at')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_bounced_at')->nullable()->after('email_verified_at');
            $table->string('email_bounce_reason', 200)->default('')->after('email_bounced_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_bounced_at', 'email_bounce_reason']);
        });
    }
};
