<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 覆寫內建的信箱驗證信，改為中文文案。
 * 連結指向前端頁面（見 AppServiceProvider::createUrlUsing），
 * 前端拿到籤名參數後再回調後端完成驗證。
 */
final class VerifyEmailNotification extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('驗證您的信箱 — '.config('app.name'))
            ->greeting('歡迎加入 '.config('app.name').'！')
            ->line('請點擊下方按鈕完成信箱驗證，即可使用完整功能。')
            ->action('驗證信箱', $url)
            ->line('連結有效期為 60 分鐘。')
            ->line('若您沒有註冊過本站帳號，請忽略本郵件。')
            ->salutation('— '.config('app.name'));
    }
}
