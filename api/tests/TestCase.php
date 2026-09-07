<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum 的 SPA 模式靠 Origin/Referer 判定请求是否来自同源前端，
        // 测试里补上这个头，走的才是真实生产路径（session cookie + CSRF），
        // 而不是退化成 token guard。
        $this->withHeaders([
            'Origin' => config('app.url'),
            'Accept' => 'application/json',
        ]);
    }
}
