<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configurePasswordRules();
        $this->configureVerificationUrl();
        $this->configureHttps();
    }

    /**
     * 开发与测试环境开启严格模式：
     * 访问未加载的关联（N+1 的源头）、写入不存在的属性都会直接抛错，
     * 问题在开发阶段暴露，而不是上线后变成慢查询。
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
    }

    /**
     * 全站统一的密码强度基线。
     * 生产环境额外启用 uncompromised()：拿密码的 SHA-1 前 5 位去 HaveIBeenPwned
     * 查是否出现在已知外泄库中（k-匿名查询，不会上送完整密码）。
     */
    private function configurePasswordRules(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(8)->letters()->numbers();

            return $this->app->isProduction()
                ? $rule->uncompromised()
                : $rule;
        });
    }

    /**
     * 邮件里的验证连结指向前端页面，由前端携带签名参数回调后端。
     * 否则用户点开会看到一段裸 JSON，而不是站内的成功页。
     */
    private function configureVerificationUrl(): void
    {
        VerifyEmail::createUrlUsing(function (User $notifiable): string {
            $signed = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
                absolute: false,
            );

            // $signed 形如 /api/auth/email/verify/1/abc?expires=...&signature=...
            $query = parse_url($signed, PHP_URL_QUERY);

            return rtrim((string) config('app.frontend_url'), '/')
                .'/verify-email?id='.$notifiable->getKey()
                .'&hash='.sha1($notifiable->getEmailForVerification())
                .'&'.$query;
        });
    }

    /** 生产环境强制 https，避免反代回源时生成出 http 的绝对连结 */
    private function configureHttps(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
