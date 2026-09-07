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
    public function 可以用正確的舊密碼修改密碼(): void
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
    public function 舊密碼錯誤時拒絕(): void
    {
        $user = User::factory()->create(['password' => 'OldPass12345']);

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'WrongOldPass1',
            'password' => 'NewPass67890',
            'password_confirmation' => 'NewPass67890',
        ])->assertStatus(422)->assertJsonPath('code', 'PASSWORD_MISMATCH');

        // 密碼必須保持不變
        $this->assertTrue(Hash::check('OldPass12345', $user->fresh()->password));
    }

    #[Test]
    public function 新密碼不可與舊密碼相同(): void
    {
        $user = User::factory()->create(['password' => 'OldPass12345']);

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'OldPass12345',
            'password' => 'OldPass12345',
            'password_confirmation' => 'OldPass12345',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    #[Test]
    public function 新密碼強度不足時拒絕(): void
    {
        $user = User::factory()->create(['password' => 'OldPass12345']);

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'OldPass12345',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    #[Test]
    public function 改密後其他裝置的會話被清除(): void
    {
        $user = User::factory()->create(['password' => 'OldPass12345']);

        // 模擬另一臺裝置的會話
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
    public function 改密會寫入審計日誌(): void
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
    public function 未登入無法改密(): void
    {
        $this->putJson('/api/me/password', [
            'current_password' => 'OldPass12345',
            'password' => 'NewPass67890',
            'password_confirmation' => 'NewPass67890',
        ])->assertStatus(401);
    }
}
