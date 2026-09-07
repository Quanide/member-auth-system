<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum 的 SPA 模式靠 Origin/Referer 判定請求是否來自同源前端，
        // 測試裡補上這個頭，走的才是真實生產路徑（session cookie + CSRF），
        // 而不是退化成 token guard。
        $this->withHeaders([
            'Origin' => config('app.url'),
            'Accept' => 'application/json',
        ]);
    }
}
