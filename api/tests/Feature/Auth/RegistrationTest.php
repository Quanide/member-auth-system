<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '王小明',
            'email' => 'xiaoming@example.com',
            'password' => 'Str0ngPass123',
            'password_confirmation' => 'Str0ngPass123',
            'agree_terms' => true,
        ], $overrides);
    }

    #[Test]
    public function 可以成功注册并自动建立会话(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.email', 'xiaoming@example.com')
            ->assertJsonPath('data.user.email_verified', false);

        $this->assertDatabaseHas('users', ['email' => 'xiaoming@example.com']);
        $this->assertAuthenticated();
    }

    #[Test]
    public function 注册后会寄出验证信(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->payload());

        $user = User::where('email', 'xiaoming@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    #[Test]
    public function 密码以雜湊储存而非明文(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->payload());

        $user = User::where('email', 'xiaoming@example.com')->firstOrFail();

        $this->assertNotSame('Str0ngPass123', $user->password);
        $this->assertTrue(password_verify('Str0ngPass123', $user->password));
    }

    #[Test]
    public function 邮箱重复时拒绝注册(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'taken@example.com']))
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['email']]);
    }

    #[Test]
    public function 邮箱大小写不同视为同一帐号(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'TAKEN@Example.com']))
            ->assertStatus(422);
    }

    #[Test]
    public function 弱密码被拒绝(): void
    {
        // 纯数字、无字母，不符合 letters() 规则
        $this->postJson('/api/auth/register', $this->payload([
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    #[Test]
    public function 两次密码不一致时被拒绝(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'password_confirmation' => 'Different123',
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    #[Test]
    public function 未同意条款时被拒绝(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['agree_terms' => false]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['agree_terms']]);
    }

    #[Test]
    public function 无法透过注册接口指定管理员角色(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->payload(['role' => 'admin']));

        $user = User::where('email', 'xiaoming@example.com')->firstOrFail();

        $this->assertSame('member', $user->role->value);
    }

    #[Test]
    public function 注册会写入审计日志(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->payload());

        $this->assertTrue(
            AuditLog::where('action', AuditAction::Register)->exists(),
        );
    }
}
