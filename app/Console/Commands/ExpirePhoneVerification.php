<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Command;

// 手機認證到期：認證日 + 12 個月就拿掉認證，會員下次登入時重新認證（照舊站 bin/expire_phone_certification.php，
// 熊叔叔 10/29「2b照舊站」）。每天 03:20 跑（routes/console.php）。會員登入時 phone.verified 也會檢查，這支是讓
// 很久沒登入的人不要一直留在會員列表、好友推薦裡。只印筆數。
class ExpirePhoneVerification extends Command
{
    protected $signature = 'phone:expire {--dry-run : 只算筆數}';

    protected $description = '手機認證超過 12 個月的，拿掉認證（要重新認證）';

    public function handle(): int
    {
        $q = User::whereNotNull('phone_verified_at')->where('phone_verified_at', '<', Phone::cutoff());
        $n = $this->option('dry-run') ? $q->count() : $q->update(['phone_verified_at' => null]);
        $this->info(sprintf('%s %d 位', $this->option('dry-run') ? '到期（沒有改）' : '已改成要重新認證', $n));

        return self::SUCCESS;
    }
}
