<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 舊站禮物搬到新版（熊叔叔 11/24 TG「阿門和禮物也要搬」）：
// - legacy_id：舊站 gift.id，重跑不重複；有值的是舊站送的（點數在舊站扣過，刪除不退點）
// - message 放寬到 60：舊站簡訊最長 60 字、2 萬多則超過 20 字；新送的照樣在表單限 20 字
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
            $table->string('message', 60)->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->dropUnique(['legacy_id']);
            $table->dropColumn('legacy_id');
            $table->string('message', 20)->default('')->change(); // 超過 20 字的舊站簡訊會被截掉
        });
    }
};
