<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 檢舉會員（設計稿 PageReport；熊叔叔 2026-10-29「全部都要做」，Asana 1220006002168383）
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            // 檢舉人或被檢舉人刪帳號時，檢舉紀錄留著（管理員還要看），所以是 nullOnDelete
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 20);            // fraud／ad／violence／hate／other
            $table->string('description', 500);
            $table->string('screenshot_path', 100)->nullable(); // 在 avatars 磁碟（私有）裡的路徑
            $table->string('status', 10)->default('open');      // open／closed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_note', 500)->default('');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
