<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// 測試站限時一次性登入連結的信
class TestLoginLink extends Notification
{
    public function __construct(private string $url, private int $minutes)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('愛諾園測試站：一次性登入連結')
            ->line("這是測試站的一次性登入連結，{$this->minutes} 分鐘內有效、只能使用一次。")
            ->action('登入測試站', $this->url)
            ->line('不是您要的，請忽略這封信。');
    }
}
