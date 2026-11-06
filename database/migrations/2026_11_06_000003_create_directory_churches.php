<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 自我介紹的教會接名錄（熊叔叔 11/6 決定 #11，按鈕選甲）：名錄公開的 church.json 每天抓一次，存一份「教會」在自己的表；
// 會員先選國家、再從名錄挑教會（找不到可以自己打名稱）。只加表和欄位，舊的 church 文字欄不動；每一項各自檢查，可重跑
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('directory_churches')) {
            Schema::create('directory_churches', function (Blueprint $t) {
                $t->unsignedInteger('oid')->primary();   // 名錄的機構編號
                $t->string('name', 150);                 // 名錄 oc（全名）
                $t->string('alias', 150)->default('');   // 名錄 on（常用名稱，跟全名不同時才有用）
                $t->char('country_code', 2)->default('');
                $t->string('country', 30)->default('');
                $t->string('city', 30)->default('');
                $t->string('district', 30)->default('');
                $t->timestamp('synced_at')->nullable();
                $t->index(['country_code', 'name']);
            });
        }
        foreach ([
            'church_country' => fn (Blueprint $t) => $t->char('church_country', 2)->nullable()->after('church'),
            'church_oid' => fn (Blueprint $t) => $t->unsignedInteger('church_oid')->nullable()->after('church'),
        ] as $col => $add) {
            if (! Schema::hasColumn('profiles', $col)) {
                Schema::table('profiles', $add);
            }
        }
    }

    public function down(): void
    {
        foreach (['church_country', 'church_oid'] as $col) {
            if (Schema::hasColumn('profiles', $col)) {
                Schema::table('profiles', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
        Schema::dropIfExists('directory_churches');
    }
};
