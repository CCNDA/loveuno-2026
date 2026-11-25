<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// 會員註銷帳號時通知管理員（寄到 LOVEUNO_CONTACT_EMAIL）。只放會員編號和原因，不放個資。
class AccountDeleted extends Notification
{
    // 2026-11-26 小芯（Asana 1220690700083353）：$byAdminId 有值＝管理員代為註銷
    public function __construct(private int $userId, private string $reason, private ?int $byAdminId = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->byAdminId !== null) {
            return (new MailMessage)
                ->subject('管理員代為註銷：#' . $this->userId . '｜愛諾園')
                ->line('管理員 #' . $this->byAdminId . ' 代為註銷了會員編號 #' . $this->userId . '（個資、照片、日記已清掉；紙條、禮物、檢舉紀錄留著）。')
                ->line('原因：' . $this->reason);
        }

        return (new MailMessage)
            ->subject('會員註銷帳號：#' . $this->userId . '｜愛諾園')
            ->line('會員編號 #' . $this->userId . ' 註銷了帳號（個資、照片、日記已清掉；紙條、禮物、檢舉紀錄留著）。')
            ->line('原因：' . $this->reason);
    }
}
