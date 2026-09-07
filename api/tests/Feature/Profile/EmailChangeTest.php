<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\EmailChangeRequest;
use App\Models\User;
use App\Notifications\VerifyNewEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 可以申请变更邮箱(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'Str0ngPass123']);

        $this->actingAs($user)->postJson('/api/me/email-change', [
            'new_email' => 'new@example.com',
            'current_password' => 'Str0ngPass123',
        ])->assertOk();

        // 验证通过前，帐号邮箱不能被改动
        $this->assertSame('old@example.com', $user->fresh()->email);
        $this->assertDatabaseHas('email_change_requests', ['new_email' => 'new@example.com']);
        Notification::assertSentTo($user, VerifyNewEmail::class);
    }

    #[Test]
    public function 密码错误时无法申请变更(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPass123']);

        $this->actingAs($user)->postJson('/api/me/email-change', [
            'new_email' => 'new@example.com',
            'current_password' => 'WrongPass123',
        ])->assertStatus(422)->assertJsonPath('code', 'PASSWORD_MISMATCH');

        $this->assertDatabaseCount('email_change_requests', 0);
    }

    #[Test]
    public function 邮箱已被他人使用时拒绝(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPass123']);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)->postJson('/api/me/email-change', [
            'new_email' => 'taken@example.com',
            'current_password' => 'Str0ngPass123',
        ])->assertStatus(422);
    }

    #[Test]
    public function 未验证邮箱的帐号不能申请变更(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'Str0ngPass123']);

        $this->actingAs($user)->postJson('/api/me/email-change', [
            'new_email' => 'new@example.com',
            'current_password' => 'Str0ngPass123',
        ])->assertStatus(403);
    }

    #[Test]
    public function 用有效token可以完成变更(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'Str0ngPass123']);

        $this->actingAs($user)->postJson('/api/me/email-change', [
            'new_email' => 'new@example.com',
            'current_password' => 'Str0ngPass123',
        ])->assertOk();

        $token = null;
        Notification::assertSentTo($user, VerifyNewEmail::class, function ($n) use (&$token) {
            $reflection = new \ReflectionProperty($n, 'token');

            $token = $reflection->getValue($n);

            return true;
        });

        $this->postJson('/api/email-change/confirm', ['token' => $token])->assertOk();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function token只能使用一次(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'Str0ngPass123']);

        $this->actingAs($user)->postJson('/api/me/email-change', [
            'new_email' => 'new@example.com',
            'current_password' => 'Str0ngPass123',
        ]);

        $token = null;
        Notification::assertSentTo($user, VerifyNewEmail::class, function ($n) use (&$token) {
            $token = (new \ReflectionProperty($n, 'token'))->getValue($n);

            return true;
        });

        $this->postJson('/api/email-change/confirm', ['token' => $token])->assertOk();
        $this->postJson('/api/email-change/confirm', ['token' => $token])->assertStatus(422);
    }

    #[Test]
    public function 过期的token被拒绝(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'Str0ngPass123']);

        $this->actingAs($user)->postJson('/api/me/email-change', [
            'new_email' => 'new@example.com',
            'current_password' => 'Str0ngPass123',
        ]);

        EmailChangeRequest::query()->update(['expires_at' => now()->subMinute()]);

        $token = null;
        Notification::assertSentTo($user, VerifyNewEmail::class, function ($n) use (&$token) {
            $token = (new \ReflectionProperty($n, 'token'))->getValue($n);

            return true;
        });

        $this->postJson('/api/email-change/confirm', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_TOKEN');
    }

    #[Test]
    public function token以雜湊储存而非明文(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'Str0ngPass123']);

        $this->actingAs($user)->postJson('/api/me/email-change', [
            'new_email' => 'new@example.com',
            'current_password' => 'Str0ngPass123',
        ]);

        $token = null;
        Notification::assertSentTo($user, VerifyNewEmail::class, function ($n) use (&$token) {
            $token = (new \ReflectionProperty($n, 'token'))->getValue($n);

            return true;
        });

        $record = EmailChangeRequest::firstOrFail();

        $this->assertNotSame($token, $record->token_hash);
        $this->assertSame(hash('sha256', $token), $record->token_hash);
    }
}
