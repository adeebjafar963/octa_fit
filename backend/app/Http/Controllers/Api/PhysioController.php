<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Physio\SendPhysioMessageRequest;
use App\Http\Requests\Physio\StorePhysioAssessmentRequest;
use App\Http\Resources\PhysioAssessmentResource;
use App\Http\Resources\PhysioMessageResource;
use App\Models\PhysioAssessment;
use App\Services\PhysioService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PhysioController extends Controller
{
    public function __construct(
        private readonly PhysioService $physioService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->physioService->listAssessments(
            $request->user(),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => PhysioAssessmentResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Physio assessments retrieved',
        );
    }

    public function store(StorePhysioAssessmentRequest $request): JsonResponse
    {
        try {
            $assessment = $this->physioService->createAssessment(
                $request->user(),
                $request->validated(),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 502);
        }

        return ApiResponse::success(
            data: (new PhysioAssessmentResource($assessment))->resolve(),
            message: 'Assessment created',
            status: 201,
        );
    }

    public function show(Request $request, PhysioAssessment $assessment): JsonResponse
    {
        $this->authorize('view', $assessment);

        $model = $this->physioService->findForUser($request->user(), $assessment->id);
        if ($model === null) {
            return ApiResponse::error('Assessment not found', 404);
        }

        return ApiResponse::success(
            data: (new PhysioAssessmentResource($model))->resolve(),
            message: 'Assessment retrieved',
        );
    }

    public function destroy(PhysioAssessment $assessment): JsonResponse
    {
        $this->authorize('delete', $assessment);
        $this->physioService->deleteAssessment($assessment);

        return ApiResponse::success(message: 'Assessment deleted');
    }

    public function sendMessage(SendPhysioMessageRequest $request, PhysioAssessment $assessment): JsonResponse
    {
        $this->authorize('update', $assessment);

        try {
            $result = $this->physioService->sendMessage(
                $request->user(),
                $assessment,
                $request->validated('message'),
                $request->validated('language'),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 502);
        }

        return ApiResponse::success(
            data: [
                'user_message' => (new PhysioMessageResource($result['user_message']))->resolve(),
                'assistant_message' => (new PhysioMessageResource($result['assistant_message']))->resolve(),
                'assessment' => (new PhysioAssessmentResource($result['assessment']))->resolve(),
            ],
            message: 'Message sent',
            status: 201,
        );
    }
}
