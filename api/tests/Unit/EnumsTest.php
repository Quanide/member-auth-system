<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 纯粹的列举逻辑，不需要资料库，因此归在 Unit。
 */
final class EnumsTest extends TestCase
{
    #[Test]
    public function 每个稽核动作都有中文标签(): void
    {
        foreach (AuditAction::cases() as $action) {
            $this->assertNotSame('', $action->label(), "{$action->value} 缺少标签");
            // 标签若与 value 相同，代表 match 分支漏了这个 case
            $this->assertNotSame($action->value, $action->label());
        }
    }

    #[Test]
    public function 每个稽核动作的等级都是合法值(): void
    {
        $allowed = ['success', 'warning', 'danger', 'info'];

        foreach (AuditAction::cases() as $action) {
            $this->assertContains($action->level(), $allowed, "{$action->value} 等级不合法");
        }
    }

    #[Test]
    public function 登入失败与帐号锁定属于危险等级(): void
    {
        $this->assertSame('danger', AuditAction::LoginFailed->level());
        $this->assertSame('danger', AuditAction::AccountLocked->level());
        $this->assertSame('danger', AuditAction::TwoFactorChallengeFailed->level());
    }

    #[Test]
    public function 只有正常状态可以登入(): void
    {
        $this->assertTrue(UserStatus::Active->canLogin());
        $this->assertFalse(UserStatus::Locked->canLogin());
        $this->assertFalse(UserStatus::Disabled->canLogin());
    }

    #[Test]
    public function 使用者状态都有中文标签(): void
    {
        foreach (UserStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }
    }

    #[Test]
    public function 角色都有中文标签(): void
    {
        $this->assertSame('会员', UserRole::Member->label());
        $this->assertSame('管理员', UserRole::Admin->label());
    }
}
