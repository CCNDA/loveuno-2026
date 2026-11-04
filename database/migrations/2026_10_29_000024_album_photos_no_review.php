<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 熊叔叔 10/29「1不用」：相簿照片不用審核。還在審核中的直接公開。
// 退回不會把它們改回審核中（分不出哪些原本是審核中），down() 不做事。
return new class extends Migration
{
    public function up(): void
    {
        DB::table('album_photos')->where('status', 'pending')->update(['status' => 'approved', 'updated_at' => now()]);
    }

    public function down(): void
    {
    }
};
