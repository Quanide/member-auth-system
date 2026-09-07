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
 * 审计日志写入。
 * 刻意吞掉异常：日志写失败不能反过来把业务主流程搞挂。
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
            Log::error('审计日志写入失败', [
                'action' => $action->value,
                'user_id' => $user?->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 取客户端真实 IP。
     * 反代场景需在 TrustProxies 中配置可信代理，否则 Laravel 不会采信 X-Forwarded-For。
     */
    public function clientIp(): ?string
    {
        return $this->request->ip();
    }
}
