<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 帳號設定「字體」：系統字／粉圓（設計稿 PageMemberSettings；熊叔叔 10/29「3A」）。Asana 1220005922286813（H）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('font_family', 10)->default('system')->after('font_size'); // system／huninn
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('font_family');
        });
    }
};
