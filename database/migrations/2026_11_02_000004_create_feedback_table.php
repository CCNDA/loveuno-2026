<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 「回報問題／建議」（熊叔叔 11/2 TG 選 fb-A：做在新版網站裡，每頁都有按鈕，自動記會員、頁面、裝置，可附截圖；
// 後台一頁看所有回應、標處理中／已處理）。先給小布、小霏測，測完再邀真實會員到測試站試用。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            // 沒登入也能送（登入頁、錯誤頁最常卡住；兩面審查 11/2）。會員註銷時由 SettingsController::destroy 刪掉他的回報和截圖
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 100)->default('');      // 沒登入的人想要回覆可以留
            $table->string('kind', 10);                     // problem／idea／other
            $table->text('body');
            $table->string('page', 300)->default('');       // 從哪一頁按的（只存本站的路徑）
            $table->string('user_agent', 300)->default(''); // 瀏覽器、手機或電腦
            $table->string('screen', 20)->default('');      // 畫面大小，例如 390x844
            $table->string('context', 100)->default('');    // 網站版本、字體大小、繁簡（都會影響版面）
            $table->string('screenshot_path', 100)->nullable(); // 私有磁碟
            $table->string('status', 10)->default('open');  // open／doing／done
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
