<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            data: ['stats' => $this->dashboardService->stats()],
            message: 'Admin dashboard',
        );
    }
}
