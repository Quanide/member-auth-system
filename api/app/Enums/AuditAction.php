<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditAction: string
{
    case Register = 'register';
    case LoginSuccess = 'login_success';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case AccountLocked = 'account_locked';

    case EmailVerificationSent = 'email_verification_sent';
    case EmailVerified = 'email_verified';

    case PasswordResetRequested = 'password_reset_requested';
    case PasswordResetCompleted = 'password_reset_completed';
    case PasswordChanged = 'password_changed';

    case ProfileUpdated = 'profile_updated';
    case AvatarUpdated = 'avatar_updated';
    case AvatarRemoved = 'avatar_removed';

    case EmailChangeRequested = 'email_change_requested';
    case EmailChanged = 'email_changed';

    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case TwoFactorChallengeFailed = 'two_factor_challenge_failed';
    case TwoFactorRecoveryUsed = 'two_factor_recovery_used';
    case TwoFactorRecoveryRegenerated = 'two_factor_recovery_regenerated';

    case SessionRevoked = 'session_revoked';
    case AllSessionsRevoked = 'all_sessions_revoked';
    case AccountDeleted = 'account_deleted';

    public function label(): string
    {
        return match ($this) {
            self::Register => '註冊帳號',
            self::LoginSuccess => '登入成功',
            self::LoginFailed => '登入失敗',
            self::Logout => '登出',
            self::AccountLocked => '帳號被鎖定',
            self::EmailVerificationSent => '發送驗證信',
            self::EmailVerified => '信箱驗證通過',
            self::PasswordResetRequested => '申請重設密碼',
            self::PasswordResetCompleted => '完成重設密碼',
            self::PasswordChanged => '修改密碼',
            self::ProfileUpdated => '修改會員資料',
            self::AvatarUpdated => '更新頭像',
            self::AvatarRemoved => '移除頭像',
            self::EmailChangeRequested => '申請變更信箱',
            self::EmailChanged => '信箱變更完成',
            self::TwoFactorEnabled => '啟用雙因素認證',
            self::TwoFactorDisabled => '關閉雙因素認證',
            self::TwoFactorChallengeFailed => '雙因素驗證失敗',
            self::TwoFactorRecoveryUsed => '使用恢復碼登入',
            self::TwoFactorRecoveryRegenerated => '重新產生恢復碼',
            self::SessionRevoked => '登出指定裝置',
            self::AllSessionsRevoked => '登出所有裝置',
            self::AccountDeleted => '註銷帳號',
        };
    }

    /** 前端用來上色：success / warning / danger / info */
    public function level(): string
    {
        return match ($this) {
            self::LoginFailed, self::AccountLocked, self::AccountDeleted,
            self::TwoFactorChallengeFailed => 'danger',
            self::PasswordChanged, self::PasswordResetCompleted, self::EmailChanged,
            self::SessionRevoked, self::AllSessionsRevoked,
            self::TwoFactorDisabled, self::TwoFactorRecoveryUsed,
            self::TwoFactorRecoveryRegenerated => 'warning',
            self::LoginSuccess, self::Register, self::EmailVerified,
            self::TwoFactorEnabled => 'success',
            default => 'info',
        };
    }
}
