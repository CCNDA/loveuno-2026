<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 手機認證（熊叔叔 2026-10-29「1A」：會員啟用要電郵認證＋手機認證；「流程電郵認證後，回來接著要驗證手機」）。
// Asana 1220005816992951（E 身份認證）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('email'); // 驗證過的手機（09xxxxxxxx），一支手機一個帳號
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('phone_pending', 20)->nullable()->after('phone_verified_at'); // 正在驗證、還沒輸入認證碼的號碼
            $table->string('phone_code_hash', 255)->nullable()->after('phone_pending');  // 認證碼只存雜湊
            $table->timestamp('phone_code_expires_at')->nullable()->after('phone_code_hash');
            $table->unsignedTinyInteger('phone_code_attempts')->default(0)->after('phone_code_expires_at');
        });

        // 這一版之前就在庫裡的帳號（示範會員、從舊站測試庫匯入的；舊站啟用本來就要手機認證）先當作已驗證，
        // 不然一登入就被擋在手機驗證頁。只補 null，可以重跑。
        DB::table('users')->whereNull('phone_verified_at')->update(['phone_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'phone_verified_at', 'phone_pending', 'phone_code_hash', 'phone_code_expires_at', 'phone_code_attempts']);
        });
    }
};
