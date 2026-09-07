<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** 全局复用同一个 hash，避免每条测试数据都跑一次 bcrypt 拖慢测试 */
    private static ?string $passwordHash = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'nickname' => fake()->optional()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$passwordHash ??= Hash::make('Password123'),
            'phone' => fake()->optional()->numerify('09########'),
            'birthday' => fake()->optional()->dateTimeBetween('-60 years', '-18 years'),
            'gender' => fake()->optional()->randomElement(['male', 'female', 'other']),
            'bio' => fake()->optional()->sentence(),
            // 显式给出：Model::shouldBeStrict() 下访问未载入的属性会抛异常，
            // factory 少写一个欄位，测试里就会炸在毫不相干的地方。
            'avatar_path' => null,
            'locked_until' => null,
            'failed_login_count' => 0,
            'last_login_at' => null,
            'last_login_ip' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Admin]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Disabled]);
    }

    /** 处于密码错误锁定期内 */
    public function locked(): static
    {
        return $this->state(fn (): array => ['locked_until' => now()->addMinutes(15)]);
    }
}
