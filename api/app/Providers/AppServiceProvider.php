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
     * 開發與測試環境開啟嚴格模式：
     * 訪問未加載的關聯（N+1 的源頭）、寫入不存在的屬性都會直接拋錯，
     * 問題在開發階段暴露，而不是上線後變成慢查詢。
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
    }

    /**
     * 全站統一的密碼強度基線。
     * 生產環境額外啟用 uncompromised()：拿密碼的 SHA-1 前 5 位去 HaveIBeenPwned
     * 查是否出現在已知外洩庫中（k-匿名查詢，不會上送完整密碼）。
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
     * 郵件裡的驗證連結指向前端頁面，由前端攜帶籤名參數回調後端。
     * 否則使用者點開會看到一段裸 JSON，而不是站內的成功頁。
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

    /** 生產環境強制 https，避免反代回源時生成出 http 的絕對連結 */
    private function configureHttps(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
