<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminStatsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class StatsController extends Controller
{
    public function __construct(private readonly AdminStatsService $stats) {}

    /** 看板一次取齐，避免前端开五个请求各自 loading */
    public function index(): JsonResponse
    {
        return ApiResponse::ok([
            'overview' => $this->stats->overview(),
            'registration_trend' => $this->stats->registrationTrend(30),
            'login_trend' => $this->stats->loginTrend(14),
            'status_distribution' => $this->stats->statusDistribution(),
            'device_distribution' => $this->stats->deviceDistribution(),
        ]);
    }
}
