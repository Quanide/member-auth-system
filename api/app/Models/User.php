<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * 白名单只放会员本人可自助修改的资料字段。
     * role / status / email / password 一律走专用方法赋值，杜绝批量赋值提权。
     */
    protected $fillable = [
        'name',
        'nickname',
        'phone',
        'birthday',
        'gender',
        'bio',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'birthday' => 'date',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    // ── 关系 ─────────────────────────────────────────────

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /** @return HasMany<EmailChangeRequest, $this> */
    public function emailChangeRequests(): HasMany
    {
        return $this->hasMany(EmailChangeRequest::class);
    }

    // ── 头像 ─────────────────────────────────────────────

    public function avatarUrl(): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    // ── 通知 ─────────────────────────────────────────────

    /** 覆写为中文文案版本 */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    // ── 状态判断 ──────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** 是否处于「密码错误过多」的临时锁定期内 */
    public function isTemporarilyLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /** 锁定还剩多少秒，前端据此提示 */
    public function lockRemainingSeconds(): int
    {
        if (! $this->isTemporarilyLocked()) {
            return 0;
        }

        return max(1, (int) Carbon::now()->diffInSeconds($this->locked_until, absolute: true));
    }

    public function canLogin(): bool
    {
        return $this->status->canLogin() && ! $this->isTemporarilyLocked();
    }

    /** 显示用名称：优先昵称 */
    public function displayName(): string
    {
        return $this->nickname !== null && $this->nickname !== ''
            ? $this->nickname
            : $this->name;
    }
}
