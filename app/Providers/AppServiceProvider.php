<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 註冊：同一個 IP 一小時最多 5 次（擋灌帳號）
        RateLimiter::for('register', fn ($request) => Limit::perHour(5)->by($request->ip()));

        // 驗證信改中文
        VerifyEmail::toMailUsing(fn ($notifiable, string $url) => (new MailMessage)
            ->subject('請驗證您的電子郵件｜愛諾園')
            ->greeting($notifiable->name . ' 您好：')
            ->line('謝謝您加入愛諾園！請按下面的按鈕驗證電子郵件，連結 60 分鐘內有效。')
            ->action('驗證電子郵件', $url)
            ->line('如果這不是您申請的，請不用理會這封信。')
            ->salutation('愛諾園 CCNDA'));

        // 重設密碼信改中文（連結 60 分鐘有效＝config auth.passwords.users.expire）
        ResetPassword::toMailUsing(fn ($notifiable, string $token) => (new MailMessage)
            ->subject('重設密碼｜愛諾園')
            ->greeting($notifiable->name . ' 您好：')
            ->line('有人申請重設您在愛諾園的密碼。請按下面的按鈕設定新密碼，連結 60 分鐘內有效，只能用一次。')
            ->action('重設密碼', route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]))
            ->line('如果這不是您申請的，請不用理會這封信，您的密碼不會改變。')
            ->salutation('愛諾園 CCNDA'));

        // 畫面上顯示幾點幾分時用 ->shown()：換成會員所在的時區（DisplayTimezone；熊叔叔 10/29「直接偵測」）
        \Illuminate\Support\Carbon::macro('shown', function () {
            /** @var \Illuminate\Support\Carbon $this */
            return $this->copy()->setTimezone(app()->bound('display_tz') ? app('display_tz') : config('app.timezone'));
        });

        // 頁尾版本號（熊叔叔 10/23 通則：每站都要有版本資訊），來源是根目錄 VERSION
        $file = base_path('VERSION');
        View::share('appVersion', is_readable($file) ? trim((string) file_get_contents($file)) : 'dev');
    }
}
