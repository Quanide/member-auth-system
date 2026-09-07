<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 审计日志：登入、改密、改资料等敏感动作留痕，供会员自查与管理端排查。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // 用户被删除后日志仍需保留，因此用 nullOnDelete 而非级联删除
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action', 40);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // 附加上下文：改了哪些字段、失败原因等
            $table->json('meta')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
