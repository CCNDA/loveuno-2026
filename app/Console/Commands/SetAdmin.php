<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

// 設定或取消管理員：php artisan user:admin <email>（--super 設成最高管理員；--off 取消，連最高管理員一起取消）。
// 平常在後台「會員管理」由最高管理員設定；這支指令用來設第一位最高管理員（熊叔叔）。不印密碼，只印 id。
class SetAdmin extends Command
{
    protected $signature = 'user:admin {email} {--super : 設成最高管理員（可以在後台設定別人當管理員）} {--off : 取消管理員}';

    protected $description = '設定或取消管理員（--super：最高管理員）';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('找不到這個 email 的帳號。');

            return self::FAILURE;
        }
        $off = (bool) $this->option('off');
        $user->forceFill([
            'is_admin' => ! $off,
            'is_super_admin' => ! $off && ($this->option('super') || $user->is_super_admin),
        ])->save();
        $this->info(sprintf('帳號 #%d 現在%s。', $user->id, $user->is_super_admin ? '是最高管理員' : ($user->is_admin ? '是管理員' : '不是管理員')));

        return self::SUCCESS;
    }
}
