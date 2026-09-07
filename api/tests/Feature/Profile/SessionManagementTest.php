<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seedSession(User $user, string $id, string $agent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.5',
            'user_agent' => $agent,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
    }

    #[Test]
    public function 可以列出登入中的裝置(): void
    {
        $user = User::factory()->create();
        $this->seedSession($user, 'device-alpha-session');
        $this->seedSession($user, 'device-beta-session', 'Mozilla/5.0 (iPhone) Safari/605');

        $response = $this->actingAs($user)->getJson('/api/me/sessions')->assertOk();

        $this->assertGreaterThanOrEqual(2, count($response->json('data.sessions')));
    }

    #[Test]
    public function 不會回傳完整的session_id(): void
    {
        $user = User::factory()->create();
        $this->seedSession($user, 'a-very-long-session-identifier-value');

        $response = $this->actingAs($user)->getJson('/api/me/sessions')->assertOk();

        $this->assertStringNotContainsString(
            'a-very-long-session-identifier-value',
            $response->getContent(),
        );
    }

    #[Test]
    public function 看不到其他人的登入紀錄(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->seedSession($other, 'someone-else-session');

        $response = $this->actingAs($user)->getJson('/api/me/sessions')->assertOk();

        foreach ($response->json('data.sessions') as $session) {
            $this->assertStringNotContainsString('someone-else', $session['id']);
        }
    }

    #[Test]
    public function 可以登出其他所有裝置(): void
    {
        $user = User::factory()->create();
        $this->seedSession($user, 'device-alpha-session');
        $this->seedSession($user, 'device-beta-session');

        $this->actingAs($user)->deleteJson('/api/me/sessions/others')->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'device-alpha-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'device-beta-session']);
    }

    #[Test]
    public function 無法撤銷他人的會話(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->seedSession($other, 'victim-session-id');

        $this->actingAs($user)
            ->deleteJson('/api/me/sessions/victim-sessi')
            ->assertStatus(404);

        $this->assertDatabaseHas('sessions', ['id' => 'victim-session-id']);
    }

    #[Test]
    public function 可以查詢自己的操作紀錄(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPass123']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $response = $this->getJson('/api/me/activities')->assertOk();

        $this->assertNotEmpty($response->json('data.items'));
        $this->assertSame('login_success', $response->json('data.items.0.action'));
    }

    #[Test]
    public function 看不到他人的操作紀錄(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $other->auditLogs()->create(['action' => 'login_success', 'ip_address' => '1.1.1.1']);

        $response = $this->actingAs($user)->getJson('/api/me/activities')->assertOk();

        $this->assertSame([], $response->json('data.items'));
    }
}
