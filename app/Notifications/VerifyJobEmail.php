<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// 職業 Email 認證信（照舊站 company_certification.php 的信）。寄到會員填的公司（學校）信箱。
class VerifyJobEmail extends Notification
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
            ->subject('職業 Email 認證｜愛諾園')
            ->greeting($this->name . ' 您好：')
            ->line('感謝您使用職業 Email 認證！請按下面的按鈕完成認證。')
            ->action('完成認證', $this->url)
            ->line('除了認證之外，我們不會把這個 Email 用在其他地方，也不會聯絡或詢問您的公司（學校）任何關於您的資料。')
            ->line('如果這不是您申請的，請不用理會這封信。')
            ->salutation('愛諾園祝您平安、喜樂！');
    }
}
