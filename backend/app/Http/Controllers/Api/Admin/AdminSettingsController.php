<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Admin\AdminSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly AdminSettingsService $settingsService,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            data: ['items' => $this->settingsService->listSafeSettings()],
            message: 'Settings retrieved',
        );
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        try {
            $items = $this->settingsService->updateSafeSettings($request->validated('settings'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            data: ['items' => $items],
            message: 'Settings updated',
        );
    }
}
