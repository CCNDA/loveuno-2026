<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 2026-11-26 小芯（Asana 1220690700083353）：後台「代為註銷」要記是哪位管理員做的。自己註銷的舊紀錄補成本人。
// 冪等：欄位已經在就不加；補值只補空的。
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('account_deletions', 'deleted_by')) {
            Schema::table('account_deletions', function (Blueprint $table) {
                $table->foreignId('deleted_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            });
        }
        DB::table('account_deletions')->whereNull('deleted_by')->update(['deleted_by' => DB::raw('user_id')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('account_deletions', 'deleted_by')) {
            Schema::table('account_deletions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('deleted_by');
            });
        }
    }
};
