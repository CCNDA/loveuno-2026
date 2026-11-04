<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// 身份認證審核結果（舊站 admin/*_approve.php 用站內信通知，新版寄 email）
class VerificationDecided extends Notification
{
    public function __construct(private string $label, private bool $approved, private string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)
            ->subject(($this->approved ? '已通過' : '沒有通過') . '：' . $this->label . '｜愛諾園')
            ->greeting($notifiable->name . ' 您好：');
        if ($this->approved) {
            $m->line('您的' . $this->label . '已經通過了，謝謝您！');
        } else {
            $m->line('很抱歉，您的' . $this->label . '沒有通過。')->line('原因：' . $this->reason)->line('您可以到「會員中心 → 個人檔案 → 身份認證」重新申請。');
        }

        return $m->action('到身份認證', route('verify.index'))->salutation('愛諾園祝您平安、喜樂！');
    }
}
