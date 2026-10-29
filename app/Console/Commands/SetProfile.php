<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Console\Command;

// 管理員改會員的性別或出生年（會員自己在 /me/edit 不能改，填錯時由管理員處理）。只印 id。
class SetProfile extends Command
{
    protected $signature = 'user:set-profile {email} {--gender= : 男 或 女} {--birth-year= : 例如 1990}';

    protected $description = '改會員的性別或出生年（管理員用）';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('找不到這個 email 的帳號。');

            return self::FAILURE;
        }
        $data = [];
        if (($g = $this->option('gender')) !== null) {
            if (! in_array($g, ['男', '女'], true)) {
                $this->error('性別只能是 男 或 女。');

                return self::FAILURE;
            }
            $data['gender'] = $g;
        }
        if (($y = $this->option('birth-year')) !== null) {
            $now = (int) now()->format('Y');
            if (! ctype_digit((string) $y) || (int) $y > $now - 18 || (int) $y < $now - 100) {
                $this->error('出生年要是 18 歲以上、合理的年份。');

                return self::FAILURE;
            }
            $data['birth_year'] = (int) $y;
        }
        if (! $data) {
            $this->error('請至少給 --gender 或 --birth-year。');

            return self::FAILURE;
        }
        Profile::updateOrCreate(['user_id' => $user->id], $data);
        $this->info(sprintf('帳號 #%d 已更新：%s', $user->id, implode('、', array_keys($data))));

        return self::SUCCESS;
    }
}
