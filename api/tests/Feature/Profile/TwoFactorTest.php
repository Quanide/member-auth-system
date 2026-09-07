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

    /** 走完整的產生 → 確認流程，回傳恢復碼 */
    private function enableFor(User $user): array
    {
        $this->actingAs($user)->postJson('/api/me/two-factor/generate')->assertOk();

        return $this->actingAs($user)
            ->postJson('/api/me/two-factor/confirm', ['code' => $this->currentCode($user)])
            ->assertOk()
            ->json('data.recovery_codes');
    }

    // ── 綁定流程 ──────────────────────────────────────

    #[Test]
    public function 可以產生密鑰與qrcode(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/me/two-factor/generate')->assertOk();

        $this->assertNotEmpty($response->json('data.secret'));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $response->json('data.qr_code'));
        $this->assertStringStartsWith('otpauth://totp/', $response->json('data.otpauth_url'));
    }

    #[Test]
    public function 產生密鑰後尚未啟用(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/me/two-factor/generate')->assertOk();

        // 只掃了碼沒驗證就算啟用的話，掃碼失敗的人會被鎖在門外
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    #[Test]
    public function 輸入正確驗證碼後啟用並取得恢復碼(): void
    {
        $user = User::factory()->create();

        $codes = $this->enableFor($user);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertCount(8, $codes);
    }

    #[Test]
    public function 驗證碼錯誤時不會啟用(): void
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
    public function 密鑰與恢復碼以加密儲存(): void
    {
        $user = User::factory()->create();
        $this->enableFor($user);

        $raw = DB::table('users')->where('id', $user->id)->first();

        // 資料庫裡應該是密文，不能直接看到明文密鑰
        $this->assertNotSame($user->fresh()->two_factor_secret, $raw->two_factor_secret);
        $this->assertStringNotContainsString(
            $user->fresh()->two_factor_secret,
            (string) $raw->two_factor_secret,
        );
    }

    // ── 登入流程 ──────────────────────────────────────

    #[Test]
    public function 啟用後登入需要第二關驗證(): void
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

        // 關鍵：此時還不算登入
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me')->assertStatus(401);
    }

    #[Test]
    public function 通過第二關後完成登入(): void
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
    public function 第二關驗證碼錯誤時拒絕(): void
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
    public function 未先通過密碼驗證不能直接打第二關(): void
    {
        $user = User::factory()->create();
        $this->enableFor($user);

        $this->post('/api/auth/logout');
        $this->app['auth']->forgetGuards();

        // 沒有 pending 狀態，直接送驗證碼應被拒絕
        $this->postJson('/api/auth/two-factor-challenge', ['code' => $this->currentCode($user)])
            ->assertStatus(401);
    }

    #[Test]
    public function 恢復碼可以登入且用後失效(): void
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

        // 同一組恢復碼不能重複使用
        $this->post('/api/auth/logout');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ngPass123',
        ])->assertOk();

        $this->postJson('/api/auth/two-factor-challenge', ['code' => $code])->assertStatus(422);
    }

    // ── 關閉與重產 ────────────────────────────────────

    #[Test]
    public function 關閉雙因素需要密碼(): void
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
    public function 可以重新產生恢復碼且舊的失效(): void
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
