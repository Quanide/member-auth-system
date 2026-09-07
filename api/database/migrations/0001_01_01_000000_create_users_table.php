<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // ── 帳號 ──
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // ── 會員資料 ──
            $table->string('name', 50);
            $table->string('nickname', 50)->nullable();
            $table->string('phone', 32)->nullable();
            $table->date('birthday')->nullable();
            $table->string('gender', 10)->nullable();     // male / female / other
            $table->string('bio', 500)->nullable();
            $table->string('avatar_path')->nullable();    // 相對 storage/app/public 的路徑

            // ── 權限與狀態 ──
            $table->string('role', 20)->default('member');   // member / admin
            $table->string('status', 20)->default('active'); // active / locked / disabled

            // ── 防爆破：帳號維度的失敗計數，比 IP 限流更難繞過 ──
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();

            // ── 登入軌跡 ──
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
