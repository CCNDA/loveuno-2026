<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

// 設定或取消管理員：php artisan user:admin <email>（--off 取消）。不印密碼，只印 id。
class SetAdmin extends Command
{
    protected $signature = 'user:admin {email} {--off : 取消管理員}';

    protected $description = '設定或取消管理員（審核大頭照用）';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('找不到這個 email 的帳號。');

            return self::FAILURE;
        }
        $user->forceFill(['is_admin' => ! $this->option('off')])->save();
        $this->info(sprintf('帳號 #%d 現在%s管理員。', $user->id, $user->is_admin ? '是' : '不是'));

        return self::SUCCESS;
    }
}
