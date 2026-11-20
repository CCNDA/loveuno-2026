<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 公測：寫入紀錄（beta_audits）與回寫舊站的待送清單（legacy_outbox）。Asana 1220586286066431、1220564106252789
// 只建新表、舊資料不動；可重跑
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('beta_audits')) {
            Schema::create('beta_audits', function (Blueprint $t) {
                $t->id();
                $t->string('actor', 40);               // user:12／sync／console／guest
                $t->string('table_name', 64);
                $t->string('record_id', 40);
                $t->string('action', 10);              // created／updated／deleted
                $t->json('before')->nullable();
                $t->json('after')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['table_name', 'record_id']);
                $t->index('created_at');
            });
        }
        if (! Schema::hasTable('legacy_outbox')) {
            Schema::create('legacy_outbox', function (Blueprint $t) {
                $t->id();
                $t->string('model', 40);
                $t->string('record_id', 40);
                $t->string('action', 10);
                $t->json('payload')->nullable();
                $t->string('status', 10)->default('pending'); // pending／sent／failed／skipped
                $t->unsignedSmallInteger('attempts')->default(0);
                $t->text('last_error')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamps();
                $t->index(['status', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_outbox');
        Schema::dropIfExists('beta_audits');
    }
};
