<?php

namespace App\Console\Commands;

use App\Support\WebPush;
use Illuminate\Console\Command;

// 產生推播用的 VAPID 金鑰並寫進 .env（已經有就不動）。私鑰不印出來，只印公鑰。
class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid {--env-file= : 要寫進哪個 .env（預設是專案的 .env）}';

    protected $description = '產生 Web Push 的 VAPID 金鑰，寫進 .env（VAPID_PUBLIC_KEY、VAPID_PRIVATE_KEY）';

    public function handle(): int
    {
        $file = $this->option('env-file') ?: base_path('.env');
        $env = (string) @file_get_contents($file);
        if (preg_match('/^VAPID_PRIVATE_KEY=.+$/m', $env)) {
            $this->info('.env 已經有 VAPID 金鑰了，不重新產生（換金鑰會讓所有裝置的推播失效）。');

            return self::SUCCESS;
        }
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($key, $pem);
        $d = openssl_pkey_get_details($key)['ec'];
        $public = WebPush::b64("\x04" . str_pad($d['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($d['y'], 32, "\x00", STR_PAD_LEFT));

        $env = preg_replace('/^VAPID_(PUBLIC|PRIVATE)_KEY=.*\n?/m', '', $env);
        $env = rtrim($env, "\n") . "\n\n# Web Push（php artisan push:vapid 產生；不要換，換了所有裝置要重新開啟推播）\nVAPID_PUBLIC_KEY={$public}\nVAPID_PRIVATE_KEY=" . base64_encode($pem) . "\n";
        file_put_contents($file, $env);
        $this->info('VAPID 金鑰寫進 ' . $file . ' 了。公鑰：' . $public);

        return self::SUCCESS;
    }
}
