<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 變更信箱的驗證信。
 * 寄往「新地址」而非帳號現址——只有真正能收信的人才能完成變更。
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
            ->subject('請驗證您的新信箱 — '.config('app.name'))
            ->greeting('您好，')
            ->line('我們收到了將帳號信箱變更為 '.$this->newEmail.' 的申請。')
            ->action('確認變更', $url)
            ->line('此連結 60 分鐘內有效。')
            ->line('若這不是您本人的操作，請忽略本郵件，並儘快修改密碼。')
            ->salutation('— '.config('app.name'));
    }

    /** 寄到新信箱，而不是 notifiable 上的現有信箱 */
    public function routeNotificationForMail(object $notifiable): string
    {
        return $this->newEmail;
    }
}
