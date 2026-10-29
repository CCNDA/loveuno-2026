<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 會員頁從這一版開始要 email 已驗證（verified）。這之前就在庫裡的帳號
// （示範會員、從舊站測試庫匯入的帳號）沒有驗證時間，不補的話一登入就被擋在驗證頁。
// 它們都不是新註冊的，所以一次補上（兩面審查 10/29）。只補 null，可以重跑。
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // 補上去的時間分不出來是哪些，不還原
    }
};
