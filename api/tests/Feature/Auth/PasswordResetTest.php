<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 可以申請重設密碼連結(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'forgot@example.com']);

        $this->postJson('/api/auth/password/forgot', ['email' => 'forgot@example.com'])
            ->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    #[Test]
    public function 信箱不存在時回應相同訊息以防枚舉(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'exists@example.com']);

        $existing = $this->postJson('/api/auth/password/forgot', ['email' => 'exists@example.com']);
        $missing = $this->postJson('/api/auth/password/forgot', ['email' => 'nobody@example.com']);

        $existing->assertOk();
        $missing->assertOk();
        $this->assertSame($existing->json('data.message'), $missing->json('data.message'));
    }

    #[Test]
    public function 可以用有效token重設密碼(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com', 'password' => 'OldPass12345']);

        $this->postJson('/api/auth/password/forgot', ['email' => 'reset@example.com'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/auth/password/reset', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'BrandNew12345',
            'password_confirmation' => 'BrandNew12345',
        ])->assertOk();

        $this->assertTrue(Hash::check('BrandNew12345', $user->fresh()->password));
    }

    #[Test]
    public function 無效token被拒絕(): void
    {
        User::factory()->create(['email' => 'reset@example.com', 'password' => 'OldPass12345']);

        $this->postJson('/api/auth/password/reset', [
            'token' => 'totally-invalid-token',
            'email' => 'reset@example.com',
            'password' => 'BrandNew12345',
            'password_confirmation' => 'BrandNew12345',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_TOKEN');
    }

    #[Test]
    public function 重設密碼會解除帳號鎖定(): void
    {
        Notification::fake();
        $user = User::factory()->locked()->create([
            'email' => 'locked@example.com',
            'failed_login_count' => 4,
        ]);

        $this->postJson('/api/auth/password/forgot', ['email' => 'locked@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->postJson('/api/auth/password/reset', [
            'token' => $token,
            'email' => 'locked@example.com',
            'password' => 'BrandNew12345',
            'password_confirmation' => 'BrandNew12345',
        ])->assertOk();

        $user->refresh();

        $this->assertNull($user->locked_until);
        $this->assertSame(0, $user->failed_login_count);
    }
}
