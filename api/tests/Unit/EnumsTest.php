<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 純粹的列舉邏輯，不需要資料庫，因此歸在 Unit。
 */
final class EnumsTest extends TestCase
{
    #[Test]
    public function 每個稽核動作都有中文標籤(): void
    {
        foreach (AuditAction::cases() as $action) {
            $this->assertNotSame('', $action->label(), "{$action->value} 缺少標籤");
            // 標籤若與 value 相同，代表 match 分支漏了這個 case
            $this->assertNotSame($action->value, $action->label());
        }
    }

    #[Test]
    public function 每個稽核動作的等級都是合法值(): void
    {
        $allowed = ['success', 'warning', 'danger', 'info'];

        foreach (AuditAction::cases() as $action) {
            $this->assertContains($action->level(), $allowed, "{$action->value} 等級不合法");
        }
    }

    #[Test]
    public function 登入失敗與帳號鎖定屬於危險等級(): void
    {
        $this->assertSame('danger', AuditAction::LoginFailed->level());
        $this->assertSame('danger', AuditAction::AccountLocked->level());
        $this->assertSame('danger', AuditAction::TwoFactorChallengeFailed->level());
    }

    #[Test]
    public function 只有正常狀態可以登入(): void
    {
        $this->assertTrue(UserStatus::Active->canLogin());
        $this->assertFalse(UserStatus::Locked->canLogin());
        $this->assertFalse(UserStatus::Disabled->canLogin());
    }

    #[Test]
    public function 使用者狀態都有中文標籤(): void
    {
        foreach (UserStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }
    }

    #[Test]
    public function 角色都有中文標籤(): void
    {
        $this->assertSame('會員', UserRole::Member->label());
        $this->assertSame('管理員', UserRole::Admin->label());
    }
}
