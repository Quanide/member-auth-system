<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '陈大文',
            'nickname' => '大文',
            'phone' => '0912345678',
            'birthday' => '1995-06-15',
            'gender' => 'male',
            'bio' => '喜欢写程式与爬山。',
        ], $overrides);
    }

    #[Test]
    public function 可以更新会员资料(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/me', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.user.name', '陈大文')
            ->assertJsonPath('data.user.nickname', '大文')
            ->assertJsonPath('data.user.phone', '0912345678')
            ->assertJsonPath('data.user.birthday', '1995-06-15')
            ->assertJsonPath('data.user.gender', 'male');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '陈大文',
            'nickname' => '大文',
        ]);
    }

    #[Test]
    public function 未登入无法更新资料(): void
    {
        $this->patchJson('/api/me', $this->payload())->assertStatus(401);
    }

    #[Test]
    public function 无法透过更新资料接口改动邮箱与角色(): void
    {
        $user = User::factory()->create([
            'email' => 'original@example.com',
            'role' => 'member',
        ]);

        $this->actingAs($user)->patchJson('/api/me', $this->payload([
            'email' => 'hacker@example.com',
            'role' => 'admin',
            'status' => 'disabled',
        ]))->assertOk();

        $user->refresh();

        // fillable 白名单挡住了越权欄位
        $this->assertSame('original@example.com', $user->email);
        $this->assertSame('member', $user->role->value);
        $this->assertSame('active', $user->status->value);
    }

    #[Test]
    public function 姓名为必填(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/me', $this->payload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    #[Test]
    public function 手机格式错误时拒绝(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/me', $this->payload(['phone' => 'abc-not-a-phone!']))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['phone']]);
    }

    #[Test]
    public function 生日不可为未来日期(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/me', $this->payload(['birthday' => now()->addDay()->toDateString()]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['birthday']]);
    }

    #[Test]
    public function 简介超过长度上限时拒绝(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/me', $this->payload(['bio' => str_repeat('字', 501)]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['bio']]);
    }

    #[Test]
    public function 空字串会被正规化为null(): void
    {
        $user = User::factory()->create(['nickname' => '旧昵称']);

        $this->actingAs($user)
            ->patchJson('/api/me', $this->payload(['nickname' => '', 'bio' => '']))
            ->assertOk();

        $user->refresh();

        $this->assertNull($user->nickname);
        $this->assertNull($user->bio);
    }

    #[Test]
    public function 更新资料会写入审计日志且只记录欄位名(): void
    {
        $user = User::factory()->create(['name' => '原名']);

        $this->actingAs($user)->patchJson('/api/me', $this->payload())->assertOk();

        $log = AuditLog::where('user_id', $user->id)
            ->where('action', AuditAction::ProfileUpdated)
            ->firstOrFail();

        $this->assertArrayHasKey('fields', $log->meta);
        $this->assertContains('name', $log->meta['fields']);
        // 日志里不该出现手机号这类个资明文
        $this->assertStringNotContainsString('0912345678', json_encode($log->meta));
    }

    #[Test]
    public function 没有实际变更时不写日志(): void
    {
        $user = User::factory()->create($this->payload(['birthday' => '1995-06-15']));

        $this->actingAs($user)->patchJson('/api/me', $this->payload())->assertOk();

        $this->assertDatabaseMissing('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::ProfileUpdated->value,
        ]);
    }
}
