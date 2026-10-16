<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeatureFlagsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class FeatureFlagsController extends Controller
{
    public function __construct(
        private readonly FeatureFlagsService $featureFlags,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->featureFlags->publicFlags(),
            message: 'Feature flags retrieved',
        );
    }
}
