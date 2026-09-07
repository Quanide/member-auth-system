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
    public function 正確憑證可以登入(): void
    {
        $user = $this->user();

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'Str0ngPass123',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function 登入會記錄時間與ip(): void
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
    public function 密碼錯誤時拒絕登入(): void
    {
        $this->user();

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'WrongPassword1',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $this->assertGuest();
    }

    #[Test]
    public function 帳號不存在與密碼錯誤返回相同錯誤碼以防枚舉(): void
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
    public function 連續失敗達上限後鎖定帳號(): void
    {
        $user = $this->user();

        for ($i = 1; $i < AuthService::MAX_FAILED_ATTEMPTS; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'member@example.com',
                'password' => 'WrongPassword1',
            ])->assertStatus(422);
        }

        // 第 5 次觸發鎖定
        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'WrongPassword1',
        ])->assertStatus(423)->assertJsonPath('code', 'ACCOUNT_LOCKED');

        $user->refresh();
        $this->assertTrue($user->isTemporarilyLocked());

        // 鎖定期內即使密碼正確也不放行
        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'Str0ngPass123',
        ])->assertStatus(423);
    }

    #[Test]
    public function 鎖定會寫入審計日誌(): void
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
    public function 成功登入後失敗計數歸零(): void
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
    public function 被停權的帳號無法登入(): void
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

        // 走完整的登入→登出流程，才測得到 session 是否真的被作廢
        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $this->assertAuthenticated();

        $this->postJson('/api/auth/logout')->assertOk();

        // 測試進程內 auth guard 會快取上一次解析出的 user，
        // 不清掉的話後續斷言看到的是陳舊狀態而非真實的登出結果。
        $this->app['auth']->forgetGuards();

        $this->assertGuest();

        // 登出後舊會話不能再取資料
        $this->getJson('/api/me')->assertStatus(401);
    }

    #[Test]
    public function 未登入時無法取得個人資料(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function 登入後可以取得個人資料且不含密碼欄位(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'member@example.com')
            ->assertJsonMissingPath('data.user.password');
    }
}
