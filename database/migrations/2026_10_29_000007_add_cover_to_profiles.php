<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 個人化封面（loveuno-web 版型；熊叔叔 10/29「1 3 4 都要」）。跟大頭照一樣要審核。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('cover_path', 100)->nullable()->after('photo_updated_at');
            $table->string('cover_status', 10)->default('')->after('cover_path');
            $table->timestamp('cover_updated_at')->nullable()->after('cover_status');
            $table->index('cover_status');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['cover_status']);
            $table->dropColumn(['cover_path', 'cover_status', 'cover_updated_at']);
        });
    }
};
