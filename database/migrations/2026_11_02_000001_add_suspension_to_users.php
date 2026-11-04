<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 後台停權（熊叔叔 11/2 TG 選「甲 只做停權」）：不能登入、別人看不到，資料留著，可以解除。
// 跟會員自己在設定關掉的「啟用帳號」（is_active）分開：停權時也把 is_active 關掉（別人看不到的規則全部沿用），
// 原本的值記在 suspend_prev_active，解除時還原。
return new class extends Migration
{
    public function up(): void
    {
        // suspended_at 也由 2026_10_30_000029（舊站搬家的停權）建：新庫照檔名會先跑那支，已經有就不重建
        $hasSuspended = Schema::hasColumn('users', 'suspended_at');
        Schema::table('users', function (Blueprint $table) use ($hasSuspended) {
            if (! $hasSuspended) {
                $table->timestamp('suspended_at')->nullable()->after('is_active');
            }
            $table->string('suspended_reason', 200)->default('')->after('suspended_at');
            $table->boolean('suspend_prev_active')->nullable()->after('suspended_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended_reason', 'suspend_prev_active']);
            // 舊站搬家的 legacy_status 還在，表示 suspended_at 也是它要用的，留給它
            if (! Schema::hasColumn('users', 'legacy_status')) {
                $table->dropColumn('suspended_at');
            }
        });
    }
};
