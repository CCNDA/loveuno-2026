<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 個人資料、自我介紹的欄位（設計稿 PageMemberProfile ProfileForm／IntroductionForm）。Asana 1220005816992951（E）。
// 真實姓名、住家電話：熊叔叔 10/29「1B」收，但只有管理員看得到（Profile 設 Hidden，畫面只在管理員頁出現）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('real_name', 30)->default('')->after('user_id');
            $table->string('home_phone', 20)->default('')->after('real_name');
            // 個人資料
            $table->string('faith', 10)->default('')->after('job');
            $table->string('marital', 10)->default('')->after('faith');
            $table->string('children', 10)->default('')->after('marital'); // 舊站 kids.txt：0～4、超過4個、保留
            $table->string('education', 10)->default('')->after('children');
            $table->string('blood_type', 10)->default('')->after('education');
            $table->unsignedSmallInteger('height')->nullable()->after('blood_type'); // cm
            $table->unsignedSmallInteger('weight')->nullable()->after('height');     // kg
            $table->string('smoking', 10)->default('')->after('weight');
            $table->string('drinking', 10)->default('')->after('smoking');
            $table->string('family_planning', 10)->default('')->after('drinking');
            // 自我介紹
            $table->string('headline', 50)->default('')->after('family_planning'); // 個人標題（設計稿 maxLength 50）
            $table->string('verse_book', 20)->default('')->after('headline');      // 最愛經文
            $table->unsignedSmallInteger('verse_chapter')->nullable()->after('verse_book');
            $table->unsignedSmallInteger('verse_verse')->nullable()->after('verse_chapter');
            $table->string('verse_text', 300)->default('')->after('verse_verse');
            $table->json('languages')->nullable()->after('verse_text'); // 1～3 種
            $table->json('hobbies')->nullable()->after('languages');
            $table->json('sports')->nullable()->after('hobbies');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['real_name', 'home_phone', 'faith', 'marital', 'children', 'education', 'blood_type', 'height', 'weight',
                'smoking', 'drinking', 'family_planning', 'headline', 'verse_book', 'verse_chapter', 'verse_verse', 'verse_text',
                'languages', 'hobbies', 'sports']);
        });
    }
};
