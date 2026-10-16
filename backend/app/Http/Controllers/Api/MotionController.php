<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Motion\AnalyzeMotionRequest;
use App\Http\Resources\MotionAnalysisResource;
use App\Services\MotionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class MotionController extends Controller
{
    public function __construct(
        private readonly MotionService $motionService,
    ) {}

    public function exercises(): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'items' => MotionService::supportedExercises(),
            ],
            message: 'Supported motion exercises',
        );
    }

    public function analyze(AnalyzeMotionRequest $request): JsonResponse
    {
        try {
            $result = $this->motionService->analyze(
                $request->validated('exercise'),
                $request->file('video'),
                $request->validated('language') ?? $request->input('language'),
            );
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $status = str_contains(strtolower($message), 'unavailable') ? 502 : 422;

            return ApiResponse::error($message, $status);
        }

        return ApiResponse::success(
            data: (new MotionAnalysisResource($result))->resolve(),
            message: 'Motion analysis completed.',
        );
    }
}
