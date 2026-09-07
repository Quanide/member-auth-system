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
     * 白名單只放會員本人可自助修改的資料欄位。
     * role / status / email / password 一律走專用方法賦值，杜絕批量賦值提權。
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
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'two_factor_confirmed_at' => 'datetime',
            // 加密儲存：資料庫外洩也無法拿去產生有效的 TOTP 驗證碼
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    // ── 關係 ─────────────────────────────────────────────

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

    // ── 頭像 ─────────────────────────────────────────────

    public function avatarUrl(): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    // ── 通知 ─────────────────────────────────────────────

    /** 覆寫為中文文案版本 */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    // ── 狀態判斷 ──────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** 是否處於「密碼錯誤過多」的臨時鎖定期內 */
    public function isTemporarilyLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /** 鎖定還剩多少秒，前端據此提示 */
    public function lockRemainingSeconds(): int
    {
        if (! $this->isTemporarilyLocked()) {
            return 0;
        }

        return max(1, (int) Carbon::now()->diffInSeconds($this->locked_until, absolute: true));
    }

    /** 是否已完成雙因素綁定（產生密鑰但沒確認不算） */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /** 還剩幾組恢復碼可用 */
    public function recoveryCodesRemaining(): int
    {
        return is_array($this->two_factor_recovery_codes)
            ? count($this->two_factor_recovery_codes)
            : 0;
    }

    public function canLogin(): bool
    {
        return $this->status->canLogin() && ! $this->isTemporarilyLocked();
    }

    /** 顯示用名稱：優先暱稱 */
    public function displayName(): string
    {
        return $this->nickname !== null && $this->nickname !== ''
            ? $this->nickname
            : $this->name;
    }
}
