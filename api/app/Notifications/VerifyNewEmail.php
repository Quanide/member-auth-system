<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 变更邮箱的验证信。
 * 寄往「新地址」而非帐号现址——只有真正能收信的人才能完成变更。
 */
final class VerifyNewEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $newEmail,
        private readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/email-change/confirm?token='.urlencode($this->token);

        return (new MailMessage)
            ->subject('请验证您的新邮箱 — '.config('app.name'))
            ->greeting('您好，')
            ->line('我们收到了将帐号邮箱变更为 '.$this->newEmail.' 的申请。')
            ->action('确认变更', $url)
            ->line('此连结 60 分钟内有效。')
            ->line('若这不是您本人的操作，请忽略本邮件，并尽快修改密码。')
            ->salutation('— '.config('app.name'));
    }

    /** 寄到新邮箱，而不是 notifiable 上的现有邮箱 */
    public function routeNotificationForMail(object $notifiable): string
    {
        return $this->newEmail;
    }
}
