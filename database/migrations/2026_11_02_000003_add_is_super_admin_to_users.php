<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 最高管理員：只有他們能在後台設定誰是管理員（熊叔叔 11/2 TG「新後台需要有地方可以設定那些ID可以是管理員，
// 不能像舊站一樣」、選「甲 只有最高管理員」：一開始只有熊叔叔，可以再加人）。
// 第一位用指令設：php artisan user:admin <email> --super
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
