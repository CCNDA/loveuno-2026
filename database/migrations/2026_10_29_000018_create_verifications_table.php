<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 身份認證：職業 Email、身分證明文件、教會週報（設計稿 PageMemberProfile IdentityVerification；
// 熊叔叔 10/29「2B」四種都要；手機認證另外在 users.phone_verified_at）。照舊站 member/*_certification*.php。
// Asana 1220005816992951（E）。
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                 // job／id／church
            $table->string('status', 15)->default('pending'); // email_sent（職業：等點信）／pending（等審核）／approved／rejected
            $table->string('company', 50)->default('');  // 職業：公司（學校）名稱
            $table->string('company_url', 200)->default('');
            $table->string('company_email', 100)->default('');
            $table->string('email_token_hash', 255)->nullable(); // 職業認證信的確認碼（只存雜湊）
            $table->string('file_path', 100)->default('');       // 身分證明、週報照片（私有磁碟）；審核完就刪
            $table->string('reject_reason', 100)->default('');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'kind']); // 每一種一筆，重新申請就覆蓋
            $table->index(['status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
