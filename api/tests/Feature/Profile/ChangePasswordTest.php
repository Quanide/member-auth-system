<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 可以用正确的旧密码修改密码(): void
    {
        $user = User::factory()->create(['password' => 'OldPass12345']);

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'OldPass12345',
            'password' => 'NewPass67890',
            'password_confirmation' => 'NewPass67890',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPass67890', $user->fresh()->password));
    }

    #[Test]
    public function 旧密码错误时拒绝(): void
    {
        $user = User::factory()->create(['password' => 'OldPass12345']);

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'WrongOldPass1',
            'password' => 'NewPass67890',
            'password_confirmation' => 'NewPass67890',
        ])->assertStatus(422)->assertJsonPath('code', 'PASSWORD_MISMATCH');

        // 密码必须保持不变
        $this->assertTrue(Hash::check('OldPass12345', $user->fresh()->password));
    }

    #[Test]
    public function 新密码不可与旧密码相同(): void
    {
        $user = User::factory()->create(['password' => 'OldPass12345']);

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'OldPass12345',
            'password' => 'OldPass12345',
            'password_confirmation' => 'OldPass12345',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    #[Test]
    public function 新密码强度不足时拒绝(): void
    {
        $user = User::factory()->create(['password' => 'OldPass12345']);

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'OldPass12345',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    #[Test]
    public function 改密后其他装置的会话被清除(): void
    {
        $user = User::factory()->create(['password' => 'OldPass12345']);

        // 模拟另一台装置的会话
        DB::table('sessions')->insert([
            'id' => 'other-device-session-id',
            'user_id' => $user->id,
            'ip_address' => '203.0.113.9',
            'user_agent' => 'OtherDevice',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'OldPass12345',
            'password' => 'NewPass67890',
            'password_confirmation' => 'NewPass67890',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session-id']);
    }

    #[Test]
    public function 改密会写入审计日志(): void
    {
        $user = User::factory()->create(['password' => 'OldPass12345']);

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'OldPass12345',
            'password' => 'NewPass67890',
            'password_confirmation' => 'NewPass67890',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::PasswordChanged->value,
        ]);
    }

    #[Test]
    public function 未登入无法改密(): void
    {
        $this->putJson('/api/me/password', [
            'current_password' => 'OldPass12345',
            'password' => 'NewPass67890',
            'password_confirmation' => 'NewPass67890',
        ])->assertStatus(401);
    }
}
