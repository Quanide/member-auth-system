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
            ->subject('重设您的密码 — '.config('app.name'))
            ->greeting('您好，')
            ->line('我们收到了重设此帐号密码的申请。')
            ->action('重设密码', $url)
            ->line("此连结将在 {$expire} 分钟后失效。")
            ->line('若这不是您本人的操作，无需任何处理，您的密码不会被更改。')
            ->salutation('— '.config('app.name'));
    }
}
