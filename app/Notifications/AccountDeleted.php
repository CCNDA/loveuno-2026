<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// 會員註銷帳號時通知管理員（寄到 LOVEUNO_CONTACT_EMAIL）。只放會員編號和原因，不放個資。
class AccountDeleted extends Notification
{
    public function __construct(private int $userId, private string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('會員註銷帳號：#' . $this->userId . '｜愛諾園')
            ->line('會員編號 #' . $this->userId . ' 註銷了帳號（個資、照片、日記已清掉；紙條、禮物、檢舉紀錄留著）。')
            ->line('原因：' . $this->reason);
    }
}
