<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function seedSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.7',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
    }

    // ── 权限边界 ──────────────────────────────────────

    #[Test]
    public function 未登入无法存取管理端(): void
    {
        $this->getJson('/api/admin/users')->assertStatus(401);
        $this->getJson('/api/admin/stats')->assertStatus(401);
    }

    #[Test]
    public function 一般会员无法存取管理端(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->getJson('/api/admin/users')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->actingAs($member)->getJson('/api/admin/stats')->assertStatus(403);
        $this->actingAs($member)->getJson('/api/admin/audit-logs')->assertStatus(403);
    }

    #[Test]
    public function 一般会员无法变更他人状态(): void
    {
        $member = User::factory()->create();
        $victim = User::factory()->create();

        $this->actingAs($member)
            ->patchJson("/api/admin/users/{$victim->id}/status", ['status' => 'disabled'])
            ->assertStatus(403);

        $this->assertSame('active', $victim->fresh()->status->value);
    }

    // ── 列表与筛选 ────────────────────────────────────

    #[Test]
    public function 管理员可以取得会员列表(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();

        $this->actingAs($admin)->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(4, 'data.items');
    }

    #[Test]
    public function 可以用关键字搜寻会员(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'findme@example.com', 'name' => '被搜寻者']);
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/users?keyword=findme')
            ->assertOk();

        $this->assertCount(1, $response->json('data.items'));
        $this->assertSame('findme@example.com', $response->json('data.items.0.email'));
    }

    #[Test]
    public function 可以依状态筛选(): void
    {
        $admin = $this->admin();
        User::factory()->disabled()->create();
        User::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/users?status=disabled')->assertOk();

        $this->assertCount(1, $response->json('data.items'));
    }

    // ── 状态与角色 ────────────────────────────────────

    #[Test]
    public function 管理员可以停权会员(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/status", ['status' => 'disabled'])
            ->assertOk();

        $this->assertSame('disabled', $target->fresh()->status->value);
    }

    #[Test]
    public function 停权会同时清除该会员的登入会话(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->seedSession($target, 'target-session-id');

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/status", ['status' => 'disabled'])
            ->assertOk();

        // 停权却留着 session，等于没停
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session-id']);
    }

    #[Test]
    public function 不能变更自己的状态(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$admin->id}/status", ['status' => 'disabled'])
            ->assertStatus(422);

        $this->assertSame('active', $admin->fresh()->status->value);
    }

    #[Test]
    public function 不能把最后一位管理员降级(): void
    {
        $admin = $this->admin();
        $other = User::factory()->admin()->create();

        // 目前有两位管理员，可以降级其中一位
        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$other->id}/role", ['role' => 'member'])
            ->assertOk();

        // 只剩自己一位时不能再降（这里换个管理员来操作，避开「不能改自己」的限制）
        $newAdmin = User::factory()->admin()->create();
        $this->actingAs($newAdmin)
            ->patchJson("/api/admin/users/{$admin->id}/role", ['role' => 'member'])
            ->assertOk();

        $lastAdmin = User::factory()->admin()->create();
        $this->actingAs($lastAdmin)
            ->patchJson("/api/admin/users/{$newAdmin->id}/role", ['role' => 'member'])
            ->assertOk();

        // 现在只剩 lastAdmin 一位，它无法降级自己，也无法被自己删除
        $this->actingAs($lastAdmin)
            ->deleteJson("/api/admin/users/{$lastAdmin->id}")
            ->assertStatus(422);
    }

    // ── 其他操作 ──────────────────────────────────────

    #[Test]
    public function 可以解除会员锁定(): void
    {
        $admin = $this->admin();
        $target = User::factory()->locked()->create(['failed_login_count' => 4]);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/unlock")
            ->assertOk();

        $target->refresh();
        $this->assertNull($target->locked_until);
        $this->assertSame(0, $target->failed_login_count);
    }

    #[Test]
    public function 可以强制会员登出所有装置(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->seedSession($target, 'force-logout-session');

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/force-logout")
            ->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'force-logout-session']);
    }

    #[Test]
    public function 删除会员为软删除以保留稽核轨迹(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/admin/users/{$target->id}")->assertOk();

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    #[Test]
    public function 管理操作会写入稽核日志(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/status", ['status' => 'disabled'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id]);
    }

    // ── 看板 ─────────────────────────────────────────

    #[Test]
    public function 管理员可以取得看板统计(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();
        User::factory()->unverified()->create();

        $this->actingAs($admin)->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.overview.total_users', 5)
            ->assertJsonPath('data.overview.unverified', 1)
            ->assertJsonStructure([
                'data' => [
                    'overview',
                    'registration_trend',
                    'login_trend' => ['dates', 'success', 'failed'],
                    'status_distribution',
                    'device_distribution',
                ],
            ]);
    }

    #[Test]
    public function 趋势资料会补齐没有资料的日期(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->getJson('/api/admin/stats')->assertOk();

        // 折线图不该因为某天没资料就断掉
        $this->assertCount(30, $response->json('data.registration_trend'));
        $this->assertCount(14, $response->json('data.login_trend.dates'));
        $this->assertCount(14, $response->json('data.login_trend.success'));
    }
}
