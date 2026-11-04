<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 舊站內容搬家（legacy:import-content）：記住是從舊站哪一筆來的，重跑不會重複。
// legacy_source：diary／pray（日記、代禱）；diary_reply／pray_reply（回覆）。Asana 1215470856990770。
return new class extends Migration
{
    public function up(): void
    {
        foreach (['diaries', 'diary_comments'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('legacy_source', 12)->nullable()->after('id');
                $table->unsignedInteger('legacy_id')->nullable()->after('legacy_source');
                $table->unique(['legacy_source', 'legacy_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['diaries', 'diary_comments'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropUnique(['legacy_source', 'legacy_id']);
                $table->dropColumn(['legacy_source', 'legacy_id']);
            });
        }
    }
};
