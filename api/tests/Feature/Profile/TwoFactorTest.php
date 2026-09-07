<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function currentCode(User $user): string
    {
        return (new Google2FA)->getCurrentOtp($user->fresh()->two_factor_secret);
    }

    /** 走完整的产生 → 确认流程，回传恢复码 */
    private function enableFor(User $user): array
    {
        $this->actingAs($user)->postJson('/api/me/two-factor/generate')->assertOk();

        return $this->actingAs($user)
            ->postJson('/api/me/two-factor/confirm', ['code' => $this->currentCode($user)])
            ->assertOk()
            ->json('data.recovery_codes');
    }

    // ── 绑定流程 ──────────────────────────────────────

    #[Test]
    public function 可以产生密钥与qrcode(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/me/two-factor/generate')->assertOk();

        $this->assertNotEmpty($response->json('data.secret'));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $response->json('data.qr_code'));
        $this->assertStringStartsWith('otpauth://totp/', $response->json('data.otpauth_url'));
    }

    #[Test]
    public function 产生密钥后尚未启用(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/me/two-factor/generate')->assertOk();

        // 只扫了码没验证就算启用的话，扫码失败的人会被锁在门外
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    public function 输入正确验证码后启用并取得恢复码(): void
    {
        $user = User::factory()->create();

        $codes = $this->enableFor($user);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertCount(8, $codes);
    }

    #[Test]
    public function 验证码错误时不会启用(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/me/two-factor/generate')->assertOk();

        $this->actingAs($user)
            ->postJson('/api/me/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_TOKEN');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    public function 密钥与恢复码以加密储存(): void
    {
        $user = User::factory()->create();
        $this->enableFor($user);

        $raw = DB::table('users')->where('id', $user->id)->first();

        // 资料库里应该是密文，不能直接看到明文密钥
        $this->assertNotSame($user->fresh()->two_factor_secret, $raw->two_factor_secret);
        $this->assertStringNotContainsString(
            $user->fresh()->two_factor_secret,
            (string) $raw->two_factor_secret,
        );
    }

    // ── 登入流程 ──────────────────────────────────────

    #[Test]
    public function 启用后登入需要第二关验证(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPass123']);
        $this->enableFor($user);

        $this->post('/api/auth/logout');
        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $this->assertTrue($response->json('data.two_factor_required'));

        // 关键：此时还不算登入
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me')->assertStatus(401);
    }

    #[Test]
    public function 通过第二关后完成登入(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPass123']);
        $this->enableFor($user);

        $this->post('/api/auth/logout');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $this->postJson('/api/auth/two-factor-challenge', ['code' => $this->currentCode($user)])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertAuthenticatedAs($user->fresh());
    }

    #[Test]
    public function 第二关验证码错误时拒绝(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPass123']);
        $this->enableFor($user);

        $this->post('/api/auth/logout');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $this->postJson('/api/auth/two-factor-challenge', ['code' => '000000'])
            ->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me')->assertStatus(401);
    }

    #[Test]
    public function 未先通过密码验证不能直接打第二关(): void
    {
        $user = User::factory()->create();
        $this->enableFor($user);

        $this->post('/api/auth/logout');
        $this->app['auth']->forgetGuards();

        // 没有 pending 状态，直接送验证码应被拒绝
        $this->postJson('/api/auth/two-factor-challenge', ['code' => $this->currentCode($user)])
            ->assertStatus(401);
    }

    #[Test]
    public function 恢复码可以登入且用后失效(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPass123']);
        $codes = $this->enableFor($user);
        $code = $codes[0];

        $this->post('/api/auth/logout');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $this->postJson('/api/auth/two-factor-challenge', ['code' => $code])->assertOk();

        $this->assertSame(7, $user->fresh()->recoveryCodesRemaining());

        // 同一组恢复码不能重复使用
        $this->post('/api/auth/logout');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $this->postJson('/api/auth/two-factor-challenge', ['code' => $code])->assertStatus(422);
    }

    // ── 关闭与重产 ────────────────────────────────────

    #[Test]
    public function 关闭双因素需要密码(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPass123']);
        $this->enableFor($user);

        $this->actingAs($user)
            ->postJson('/api/me/two-factor/disable', ['password' => 'WrongPass123'])
            ->assertStatus(422);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user)
            ->postJson('/api/me/two-factor/disable', ['password' => 'Str0ngPass123'])
            ->assertOk();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    public function 可以重新产生恢复码且旧的失效(): void
    {
        $user = User::factory()->create(['password' => 'Str0ngPass123']);
        $oldCodes = $this->enableFor($user);

        $newCodes = $this->actingAs($user)
            ->postJson('/api/me/two-factor/recovery-codes', ['password' => 'Str0ngPass123'])
            ->assertOk()
            ->json('data.recovery_codes');

        $this->assertCount(8, $newCodes);
        $this->assertEmpty(array_intersect($oldCodes, $newCodes));
    }
}
