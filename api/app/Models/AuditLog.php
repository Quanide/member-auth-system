<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property AuditAction $action
 */
class AuditLog extends Model
{
    /** 只有 created_at，沒有 updated_at：日誌寫入後不可變更 */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
