<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 審計日誌寫入。
 * 刻意吞掉異常：日誌寫失敗不能反過來把業務主流程搞掛。
 */
final class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(AuditAction $action, ?User $user = null, array $meta = []): void
    {
        try {
            AuditLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'ip_address' => $this->clientIp(),
                'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
                'meta' => $meta === [] ? null : $meta,
            ]);
        } catch (Throwable $e) {
            Log::error('審計日誌寫入失敗', [
                'action' => $action->value,
                'user_id' => $user?->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 取用戶端真實 IP。
     * 反代場景需在 TrustProxies 中配置可信代理，否則 Laravel 不會採信 X-Forwarded-For。
     */
    public function clientIp(): ?string
    {
        return $this->request->ip();
    }
}
