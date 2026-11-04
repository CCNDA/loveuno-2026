<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 註銷帳號改成「清掉個資、帳號那一列留著」（兩面審查 10/29：整列刪掉會把被騷擾的人收到的紙條、禮物證據一起刪掉，
// 檢舉也看不出被檢舉的是誰）；改 Email（設計稿 PageMemberSettings「E-mail〔修改〕」）。Asana 1220005922286813（H）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('script'); // 註銷的時間（個資已清掉）
            $table->string('email_pending', 100)->nullable()->after('email'); // 改 Email：新信箱點了確認信才換
        });
        Schema::create('account_deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // 帳號列留著，所以這筆也留著
            $table->string('reason', 1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'email_pending']);
        });
    }
};
