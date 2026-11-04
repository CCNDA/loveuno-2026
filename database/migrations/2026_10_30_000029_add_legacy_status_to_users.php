<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 舊站整批搬家（熊叔叔 10/30「3都搬」：所有狀態都搬）。Asana 1215470856990770。
// legacy_status：舊站 basic.status 原值（2 交友會員、4 社群會員、6 特約作者、1／3／5 未啟用、負數停權…），留著查對。
// suspended_at：停權。有值就不能登入（LoginController），也不會出現在列表（同時 is_active = false）。
return new class extends Migration
{
    public function up(): void
    {
        // suspended_at 也由 2026_11_02_000001（後台停權）建：已經有就不重建（測試站的庫是先跑 11/2 那支）
        $hasSuspended = Schema::hasColumn('users', 'suspended_at');
        Schema::table('users', function (Blueprint $table) use ($hasSuspended) {
            $table->smallInteger('legacy_status')->nullable()->after('legacy_id');
            if (! $hasSuspended) {
                $table->timestamp('suspended_at')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('legacy_status');
            // 後台停權的欄位還在，表示 suspended_at 也是它要用的，留給它
            if (! Schema::hasColumn('users', 'suspended_reason')) {
                $table->dropColumn('suspended_at');
            }
        });
    }
};
