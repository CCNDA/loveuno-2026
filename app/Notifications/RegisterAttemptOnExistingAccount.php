<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// 有人用已經註冊過的 email 再申請一次。畫面上不說「已經註冊過」（不洩漏），改成寫信告訴信箱主人。
class RegisterAttemptOnExistingAccount extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('有人用您的信箱申請加入愛諾園')
            ->greeting($notifiable->name . ' 您好：')
            ->line('剛剛有人用這個電子郵件申請加入愛諾園，但這個信箱已經是會員了，所以我們沒有建立新帳號。')
            ->line('如果是您自己，直接登入就可以了。')
            ->action('登入愛諾園', route('login'))
            ->line('如果不是您，請不用理會這封信，您的帳號沒有任何改變。')
            ->salutation('愛諾園 CCNDA');
    }
}
