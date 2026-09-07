<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 统一回应外壳的结构验证。
 *
 * 前端依赖 { ok, data } / { ok, code, message } 这个契约，
 * 改坏了会让所有页面的错误处理一起失效，值得用测试钉住。
 *
 * 这里需要 Laravel 的 response() 辅助函式，所以继承框架的 TestCase；
 * 但不使用 RefreshDatabase，全程不碰资料库，执行速度与纯单元测试相当。
 */
final class ApiResponseTest extends TestCase
{
    #[Test]
    public function 成功回应包含ok与data(): void
    {
        $response = ApiResponse::ok(['foo' => 'bar']);
        $body = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['ok']);
        $this->assertSame(['foo' => 'bar'], $body['data']);
    }

    #[Test]
    public function 可以指定成功状态码(): void
    {
        $this->assertSame(201, ApiResponse::ok(['id' => 1], 201)->getStatusCode());
    }

    #[Test]
    public function 讯息回应会包在data底下(): void
    {
        $body = json_decode(ApiResponse::message('已完成')->getContent(), true);

        $this->assertTrue($body['ok']);
        $this->assertSame('已完成', $body['data']['message']);
    }

    #[Test]
    public function 错误回应包含错误码与讯息(): void
    {
        $response = ApiResponse::error(ErrorCode::INVALID_CREDENTIALS, '邮箱或密码不正确', 422);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($body['ok']);
        $this->assertSame('INVALID_CREDENTIALS', $body['code']);
        $this->assertSame('邮箱或密码不正确', $body['message']);
        // 没有欄位错误时不该出现空的 errors 键，避免前端误判
        $this->assertArrayNotHasKey('errors', $body);
    }

    #[Test]
    public function 欄位错误会被带出(): void
    {
        $body = json_decode(
            ApiResponse::error(
                ErrorCode::VALIDATION_FAILED,
                '资料有误',
                422,
                ['email' => ['邮箱格式不正确']],
            )->getContent(),
            true,
        );

        $this->assertSame(['邮箱格式不正确'], $body['errors']['email']);
    }

    #[Test]
    public function 额外栏位会被合并进回应(): void
    {
        $body = json_decode(
            ApiResponse::error(
                ErrorCode::TOO_MANY_REQUESTS,
                '太频繁',
                429,
                extra: ['retry_after' => 60],
            )->getContent(),
            true,
        );

        $this->assertSame(60, $body['retry_after']);
    }
}
