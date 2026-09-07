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
    public function 可以成功註冊並自動建立會話(): void
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
    public function 註冊後會寄出驗證信(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->payload());

        $user = User::where('email', 'xiaoming@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    #[Test]
    public function 密碼以雜湊儲存而非明文(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->payload());

        $user = User::where('email', 'xiaoming@example.com')->firstOrFail();

        $this->assertNotSame('Str0ngPass123', $user->password);
        $this->assertTrue(password_verify('Str0ngPass123', $user->password));
    }

    #[Test]
    public function 信箱重複時拒絕註冊(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'taken@example.com']))
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['email']]);
    }

    #[Test]
    public function 信箱大小寫不同視為同一帳號(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'TAKEN@Example.com']))
            ->assertStatus(422);
    }

    #[Test]
    public function 弱密碼被拒絕(): void
    {
        // 純數字、無字母，不符合 letters() 規則
        $this->postJson('/api/auth/register', $this->payload([
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    #[Test]
    public function 兩次密碼不一致時被拒絕(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'password_confirmation' => 'Different123',
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    #[Test]
    public function 未同意條款時被拒絕(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['agree_terms' => false]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['agree_terms']]);
    }

    #[Test]
    public function 無法透過註冊接口指定管理員角色(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->payload(['role' => 'admin']));

        $user = User::where('email', 'xiaoming@example.com')->firstOrFail();

        $this->assertSame('member', $user->role->value);
    }

    #[Test]
    public function 註冊會寫入審計日誌(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->payload());

        $this->assertTrue(
            AuditLog::where('action', AuditAction::Register)->exists(),
        );
    }
}
