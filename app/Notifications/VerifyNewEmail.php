<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// 改 Email：寄到新信箱的確認信
class VerifyNewEmail extends Notification
{
    public function __construct(private string $name, private string $url)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('確認新的電子郵件｜愛諾園')
            ->greeting($this->name . ' 您好：')
            ->line('您申請把愛諾園的登入電子郵件換成這個信箱。請登入愛諾園後按下面的按鈕確認，連結 60 分鐘內有效。')
            ->action('確認換成這個信箱', $this->url)
            ->line('如果這不是您申請的，請不用理會這封信。')
            ->salutation('愛諾園 CCNDA');
    }
}
