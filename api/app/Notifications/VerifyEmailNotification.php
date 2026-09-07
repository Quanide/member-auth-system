<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 覆写内建的邮箱验证信，改为中文文案。
 * 连结指向前端页面（见 AppServiceProvider::createUrlUsing），
 * 前端拿到签名参数后再回调后端完成验证。
 */
final class VerifyEmailNotification extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('验证您的邮箱 — '.config('app.name'))
            ->greeting('欢迎加入 '.config('app.name').'！')
            ->line('请点击下方按钮完成邮箱验证，即可使用完整功能。')
            ->action('验证邮箱', $url)
            ->line('连结有效期为 60 分钟。')
            ->line('若您没有注册过本站帐号，请忽略本邮件。')
            ->salutation('— '.config('app.name'));
    }
}
