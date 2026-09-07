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
        // 供评审直接登入体验的示范帐号
        User::firstOrCreate(
            ['email' => 'demo@wanghui.aipod.works'],
            [
                'name' => '示范会员',
                'nickname' => 'Demo',
                'password' => 'Demo12345',
                'email_verified_at' => now(),
                'role' => UserRole::Member,
                'bio' => '这是用于展示的示范帐号，可自由修改资料体验功能。',
            ],
        );

        User::firstOrCreate(
            ['email' => 'admin@wanghui.aipod.works'],
            [
                'name' => '系统管理员',
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
