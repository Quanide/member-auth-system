<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'member@example.com',
            'password' => 'Str0ngPass123',
        ], $overrides));
    }

    #[Test]
    public function 正确凭证可以登入(): void
    {
        $user = $this->user();

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'Str0ngPass123',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function 登入会记录时间与ip(): void
    {
        $user = $this->user();

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    #[Test]
    public function 密码错误时拒绝登入(): void
    {
        $this->user();

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'WrongPassword1',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $this->assertGuest();
    }

    #[Test]
    public function 帐号不存在与密码错误返回相同错误码以防枚举(): void
    {
        $this->user();

        $notFound = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'Whatever123',
        ]);

        $wrongPassword = $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'WrongPassword1',
        ]);

        $this->assertSame($notFound->json('code'), $wrongPassword->json('code'));
        $this->assertSame($notFound->json('message'), $wrongPassword->json('message'));
    }

    #[Test]
    public function 连续失败达上限后锁定帐号(): void
    {
        $user = $this->user();

        for ($i = 1; $i < AuthService::MAX_FAILED_ATTEMPTS; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'member@example.com',
                'password' => 'WrongPassword1',
            ])->assertStatus(422);
        }

        // 第 5 次触发锁定
        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'WrongPassword1',
        ])->assertStatus(423)->assertJsonPath('code', 'ACCOUNT_LOCKED');

        $user->refresh();
        $this->assertTrue($user->isTemporarilyLocked());

        // 锁定期内即使密码正确也不放行
        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'Str0ngPass123',
        ])->assertStatus(423);
    }

    #[Test]
    public function 锁定会写入审计日志(): void
    {
        $this->user();

        for ($i = 0; $i < AuthService::MAX_FAILED_ATTEMPTS; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'member@example.com',
                'password' => 'WrongPassword1',
            ]);
        }

        $this->assertTrue(AuditLog::where('action', AuditAction::AccountLocked)->exists());
    }

    #[Test]
    public function 成功登入后失败计数归零(): void
    {
        $user = $this->user();

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'WrongPassword1',
        ]);

        $this->assertSame(1, $user->fresh()->failed_login_count);

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $this->assertSame(0, $user->fresh()->failed_login_count);
    }

    #[Test]
    public function 被停权的帐号无法登入(): void
    {
        $this->user();
        User::where('email', 'member@example.com')->update(['status' => 'disabled']);

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'Str0ngPass123',
        ])->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    #[Test]
    public function 可以登出(): void
    {
        $this->user();

        // 走完整的登入→登出流程，才测得到 session 是否真的被作废
        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $this->assertAuthenticated();

        $this->postJson('/api/auth/logout')->assertOk();

        // 测试进程内 auth guard 会缓存上一次解析出的 user，
        // 不清掉的话后续断言看到的是陈旧状态而非真实的登出结果。
        $this->app['auth']->forgetGuards();

        $this->assertGuest();

        // 登出后旧会话不能再取资料
        $this->getJson('/api/me')->assertStatus(401);
    }

    #[Test]
    public function 未登入时无法取得个人资料(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function 登入后可以取得个人资料且不含密码欄位(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'member@example.com')
            ->assertJsonMissingPath('data.user.password');
    }
}
