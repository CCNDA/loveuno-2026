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
        Schema::table('users', function (Blueprint $table) {
            $table->smallInteger('legacy_status')->nullable()->after('legacy_id');
            $table->timestamp('suspended_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['legacy_status', 'suspended_at']);
        });
    }
};
