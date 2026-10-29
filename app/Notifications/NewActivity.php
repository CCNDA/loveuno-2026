<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// 有人傳紙條、送禮物給我（帳號設定「Email 通知」打開才寄；舊站 gift.php 也有「禮物通知」信）。
// 信裡不放紙條內容、禮物簡訊，只說有新的，請登入看（免得在信箱裡外流）。
class NewActivity extends Notification
{
    public function __construct(private string $what, private string $fromName, private string $url)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->fromName . ' ' . $this->what . '｜愛諾園')
            ->greeting($notifiable->name . ' 您好：')
            ->line($this->fromName . ' ' . $this->what . '，請登入愛諾園看看。')
            ->action('到愛諾園', $this->url)
            ->line('不想收到這類通知，可以到「會員中心 → 帳號管理 → 帳號設定」把「Email 通知」關掉。')
            ->salutation('愛諾園祝您平安、喜樂！');
    }
}
