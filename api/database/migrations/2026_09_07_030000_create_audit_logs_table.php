<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 審計日誌：登入、改密、改資料等敏感動作留痕，供會員自查與管理端排查。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // 使用者被刪除後日誌仍需保留，因此用 nullOnDelete 而非級聯刪除
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action', 40);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // 附加上下文：改了哪些欄位、失敗原因等
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
