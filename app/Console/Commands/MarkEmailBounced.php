<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// 退信登記：php artisan user:email-bounce <email> --reason="…"（硬退＝信箱不存在／被拒）；--soft 只記 log（信箱滿、暫時失敗）。
// 硬退：停寄通知、會員下次登入被帶到驗證頁，可以重寄驗證信或改信箱。不印 email 以外的個資，只印帳號 id。
// 之後接 SES 退信通知（SNS）時也走 User::markEmailBounced()。
class MarkEmailBounced extends Command
{
    protected $signature = 'user:email-bounce {email} {--reason= : 退信原因（例：550 user unknown）} {--soft : 暫時性退信，只記錄不停寄}';

    protected $description = '登記退信：硬退停寄通知並請會員重新驗證或改信箱';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('找不到這個 email 的帳號。');

            return self::FAILURE;
        }
        $reason = (string) ($this->option('reason') ?: '信箱寄不到');
        if ($this->option('soft')) {
            Log::info('email soft bounce', ['user_id' => $user->id, 'reason' => $reason]);
            $this->info(sprintf('帳號 #%d：暫時性退信，只記錄，沒有停寄。', $user->id));

            return self::SUCCESS;
        }
        $user->markEmailBounced($reason);
        $this->info(sprintf('帳號 #%d：已標成信箱無效，停寄通知；下次登入會請他重新驗證或改信箱。', $user->id));

        return self::SUCCESS;
    }
}
