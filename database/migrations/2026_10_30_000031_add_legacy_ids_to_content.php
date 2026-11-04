<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 舊站內容搬家（二）：紙條（舊站留言板）、相簿、約會、愛的見證也記住從舊站哪一筆來的，重跑不重複。Asana 1215470856990770。
return new class extends Migration
{
    private array $tables = ['notes', 'album_photos', 'meetups', 'love_stories'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('legacy_source', 16)->nullable()->after('id');
                $table->unsignedInteger('legacy_id')->nullable()->after('legacy_source');
                $table->unique(['legacy_source', 'legacy_id']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropUnique(['legacy_source', 'legacy_id']);
                $table->dropColumn(['legacy_source', 'legacy_id']);
            });
        }
    }
};
