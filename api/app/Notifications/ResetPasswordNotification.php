<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

final class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/reset-password?token='.$this->token
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());

        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('重設您的密碼 — '.config('app.name'))
            ->greeting('您好，')
            ->line('我們收到了重設此帳號密碼的申請。')
            ->action('重設密碼', $url)
            ->line("此連結將在 {$expire} 分鐘後失效。")
            ->line('若這不是您本人的操作，無需任何處理，您的密碼不會被更改。')
            ->salutation('— '.config('app.name'));
    }
}
