<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoachMemory;
use App\Services\CoachService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 11H — user-facing Coach memory controls (list / deactivate / clear).
 */
class CoachMemoryController extends Controller
{
    public function __construct(
        private readonly CoachService $coachService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CoachMemory::class);

        $items = $this->coachService->listMemories($request->user());

        return ApiResponse::success(
            data: [
                'schema_version' => '11h.1',
                'items' => $items,
                'count' => count($items),
            ],
            message: 'Coach memories retrieved',
        );
    }

    public function deactivate(Request $request, CoachMemory $memory): JsonResponse
    {
        $this->authorize('update', $memory);

        $updated = $this->coachService->deactivateMemory($request->user(), $memory);

        return ApiResponse::success(
            data: $updated->toApiArray(),
            message: 'Coach memory deactivated',
        );
    }

    public function destroy(Request $request, CoachMemory $memory): JsonResponse
    {
        $this->authorize('delete', $memory);
        $this->coachService->deleteMemory($request->user(), $memory);

        return ApiResponse::success(message: 'Coach memory deleted');
    }

    public function clear(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CoachMemory::class);
        $deleted = $this->coachService->clearMemories($request->user());

        return ApiResponse::success(
            data: ['deleted' => $deleted],
            message: 'Coach memories cleared',
        );
    }
}
