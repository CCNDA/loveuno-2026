<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// 聯絡我們：寄給愛諾園的信箱。Reply-To 設成會員填的 Email，直接按回覆就好。
class ContactReceived extends Notification
{
    public function __construct(private ContactMessage $msg)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = $this->msg;

        return (new MailMessage)
            ->subject('聯絡我們：' . $m->subjectLabel() . '（#' . $m->id . '）｜愛諾園')
            ->replyTo($m->email, $m->name)
            ->line('姓名：' . $m->name)
            ->line('Email：' . $m->email)
            ->line('會員編號：' . ($m->user_id ?? '（沒有登入）'))
            ->line('聯絡事項：' . $m->subjectLabel())
            ->line('內容：')
            ->line($m->body);
    }
}
