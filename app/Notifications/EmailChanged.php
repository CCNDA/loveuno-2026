<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// 改 Email：換好後寄到「舊」信箱，萬一不是本人改的，本人才知道
class EmailChanged extends Notification
{
    public function __construct(private string $name)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('您的登入電子郵件已經換了｜愛諾園')
            ->greeting($this->name . ' 您好：')
            ->line('您在愛諾園的登入電子郵件剛剛換成另一個信箱了。')
            ->line('如果不是您本人換的，請馬上寫信到 ' . config('loveuno.contact_email') . ' 跟我們聯繫。')
            ->salutation('愛諾園 CCNDA');
    }
}
