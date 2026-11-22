<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 會員編號沿用舊站（熊叔叔 11/22 TG「會員編號應該要沿用吧」）之後，新版自己建的帳號從 5,000,000 開始編，
// 跟舊站編號（目前 1,0xx,xxx，公測期間還在長）拉開，不會被新版先用掉、愛的見證填編號也不會對到別人（小布 11/22 21:43 審查第 1 項）。
// 只調高不調低；匯入時明確給的舊站編號照樣寫得進去
return new class extends Migration
{
    private const START = 5000000;

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        $next = (int) DB::selectOne('SELECT AUTO_INCREMENT a FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', ['users'])->a;
        if ($next < self::START) {
            DB::statement('ALTER TABLE users AUTO_INCREMENT = ' . self::START);
        }
    }

    public function down(): void
    {
        // 不調回去：已經用掉的編號不能再發
    }
};
