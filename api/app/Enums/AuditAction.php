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
            self::Register => '注册帐号',
            self::LoginSuccess => '登入成功',
            self::LoginFailed => '登入失败',
            self::Logout => '登出',
            self::AccountLocked => '帐号被锁定',
            self::EmailVerificationSent => '发送验证信',
            self::EmailVerified => '邮箱验证通过',
            self::PasswordResetRequested => '申请重设密码',
            self::PasswordResetCompleted => '完成重设密码',
            self::PasswordChanged => '修改密码',
            self::ProfileUpdated => '修改会员资料',
            self::AvatarUpdated => '更新头像',
            self::AvatarRemoved => '移除头像',
            self::EmailChangeRequested => '申请变更邮箱',
            self::EmailChanged => '邮箱变更完成',
            self::TwoFactorEnabled => '启用双因素认证',
            self::TwoFactorDisabled => '关闭双因素认证',
            self::TwoFactorChallengeFailed => '双因素验证失败',
            self::TwoFactorRecoveryUsed => '使用恢复码登入',
            self::TwoFactorRecoveryRegenerated => '重新产生恢复码',
            self::SessionRevoked => '登出指定装置',
            self::AllSessionsRevoked => '登出所有装置',
            self::AccountDeleted => '注销帐号',
        };
    }

    /** 前端用来上色：success / warning / danger / info */
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
