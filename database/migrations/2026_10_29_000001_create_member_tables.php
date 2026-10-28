<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 會員、個人檔案、心情日記（11/8 展示版，Asana 1215470856990770）
// 舊站 basic／profile／diary 欄位很多，這裡只先建展示要用的；之後照決策地圖逐模組加。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 舊站 basic.id（7 位數）。匯入的帳號才有，新註冊的是 null。
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
            // 示範資料（假會員），正式開放前要整批刪掉。
            $table->boolean('is_demo')->default(false)->index()->after('remember_token');
            $table->timestamp('last_login_at')->nullable()->after('is_demo');
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('gender', 10)->default('');      // 男／女／空白
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('live_place', 20)->default('');
            $table->string('church', 100)->default('');
            $table->boolean('church_visible')->default(false);
            $table->string('job', 20)->default('');
            $table->text('intro')->nullable();
            $table->text('hope')->nullable();               // 期待認識什麼樣的人
            $table->timestamps();
        });

        Schema::create('diaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->string('visibility', 10)->default('public'); // public／private
            $table->date('published_on');
            $table->timestamps();
            $table->index(['visibility', 'published_on']);
            $table->index(['user_id', 'published_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diaries');
        Schema::dropIfExists('profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['legacy_id', 'is_demo', 'last_login_at']);
        });
    }
};
