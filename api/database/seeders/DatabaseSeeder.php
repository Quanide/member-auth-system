<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 供評審直接登入體驗的示範帳號
        User::firstOrCreate(
            ['email' => 'demo@wanghui.aipod.works'],
            [
                'name' => '示範會員',
                'nickname' => 'Demo',
                'password' => 'Demo12345',
                'email_verified_at' => now(),
                'role' => UserRole::Member,
                'bio' => '這是用於展示的示範帳號，可自由修改資料體驗功能。',
            ],
        );

        User::firstOrCreate(
            ['email' => 'admin@wanghui.aipod.works'],
            [
                'name' => '系統管理員',
                'nickname' => 'Admin',
                'password' => 'Admin12345',
                'email_verified_at' => now(),
                'role' => UserRole::Admin,
            ],
        );

        if (app()->environment('local')) {
            User::factory()->count(20)->create();
        }
    }
}
