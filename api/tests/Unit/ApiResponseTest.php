<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 統一回應外殼的結構驗證。
 *
 * 前端依賴 { ok, data } / { ok, code, message } 這個契約，
 * 改壞了會讓所有頁面的錯誤處理一起失效，值得用測試釘住。
 *
 * 這裡需要 Laravel 的 response() 輔助函式，所以繼承框架的 TestCase；
 * 但不使用 RefreshDatabase，全程不碰資料庫，執行速度與純單元測試相當。
 */
final class ApiResponseTest extends TestCase
{
    #[Test]
    public function 成功回應包含ok與data(): void
    {
        $response = ApiResponse::ok(['foo' => 'bar']);
        $body = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['ok']);
        $this->assertSame(['foo' => 'bar'], $body['data']);
    }

    #[Test]
    public function 可以指定成功狀態碼(): void
    {
        $this->assertSame(201, ApiResponse::ok(['id' => 1], 201)->getStatusCode());
    }

    #[Test]
    public function 訊息回應會包在data底下(): void
    {
        $body = json_decode(ApiResponse::message('已完成')->getContent(), true);

        $this->assertTrue($body['ok']);
        $this->assertSame('已完成', $body['data']['message']);
    }

    #[Test]
    public function 錯誤回應包含錯誤碼與訊息(): void
    {
        $response = ApiResponse::error(ErrorCode::INVALID_CREDENTIALS, '信箱或密碼不正確', 422);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($body['ok']);
        $this->assertSame('INVALID_CREDENTIALS', $body['code']);
        $this->assertSame('信箱或密碼不正確', $body['message']);
        // 沒有欄位錯誤時不該出現空的 errors 鍵，避免前端誤判
        $this->assertArrayNotHasKey('errors', $body);
    }

    #[Test]
    public function 欄位錯誤會被帶出(): void
    {
        $body = json_decode(
            ApiResponse::error(
                ErrorCode::VALIDATION_FAILED,
                '資料有誤',
                422,
                ['email' => ['信箱格式不正確']],
            )->getContent(),
            true,
        );

        $this->assertSame(['信箱格式不正確'], $body['errors']['email']);
    }

    #[Test]
    public function 額外欄位會被合併進回應(): void
    {
        $body = json_decode(
            ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS,
                '太頻繁',
                429,
                extra: ['retry_after' => 60],
            )->getContent(),
            true,
        );

        $this->assertSame(60, $body['retry_after']);
    }
}
