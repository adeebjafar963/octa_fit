<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExerciseResource;
use App\Http\Resources\TrainingCategoryResource;
use App\Http\Resources\TrainingProgramResource;
use App\Http\Resources\WorkoutSessionResource;
use App\Models\WorkoutSession;
use App\Services\TrainingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function __construct(
        private readonly TrainingService $trainingService,
    ) {}

    public function disciplines(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->trainingService->listDisciplines(),
            message: 'Training disciplines retrieved',
        );
    }

    public function levels(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', '');
        $levels = collect(TrainingService::LEVELS)->map(function (string $level) use ($type) {
            $categories = $this->trainingService->listCategories($type !== '' ? $type : null, $level);

            return [
                'slug' => $level,
                'name_en' => ucfirst($level),
                'name_ar' => match ($level) {
                    'beginner' => 'مبتدئ',
                    'intermediate' => 'متوسط',
                    'advanced' => 'متقدم',
                    default => $level,
                },
                'categories_count' => $categories->count(),
                'exercises_count' => (int) $categories->sum('exercises_count'),
            ];
        })->values()->all();

        return ApiResponse::success(data: $levels, message: 'Training levels retrieved');
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = $this->trainingService->listCategories(
            $request->query('type'),
            $request->query('level'),
        );

        return ApiResponse::success(
            data: TrainingCategoryResource::collection($categories)->resolve(),
            message: 'Training categories retrieved',
        );
    }

    public function showCategory(string $category): JsonResponse
    {
        $model = $this->trainingService->findCategory($category);

        if ($model === null) {
            return ApiResponse::error('Category not found', 404);
        }

        $model->loadCount(['exercises as exercises_count' => fn ($q) => $q->active()]);

        return ApiResponse::success(
            data: (new TrainingCategoryResource($model))->resolve(),
            message: 'Training category retrieved',
        );
    }

    public function programs(Request $request): JsonResponse
    {
        $programs = $this->trainingService->listPrograms(
            $request->query('type'),
            $request->query('level'),
            $request->query('category_id') !== null ? (int) $request->query('category_id') : null,
        );

        return ApiResponse::success(
            data: TrainingProgramResource::collection($programs)->resolve(),
            message: 'Training programs retrieved',
        );
    }

    public function showProgram(string $program): JsonResponse
    {
        $model = $this->trainingService->findProgram($program);
        if ($model === null) {
            return ApiResponse::error('Program not found', 404);
        }

        return ApiResponse::success(
            data: (new TrainingProgramResource($model))->resolve(),
            message: 'Training program retrieved',
        );
    }

    public function exercises(Request $request): JsonResponse
    {
        $paginator = $this->trainingService->listExercises($request->all());

        return ApiResponse::success(
            data: [
                'items' => ExerciseResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Exercises retrieved',
        );
    }

    public function showExercise(int $exercise): JsonResponse
    {
        $model = $this->trainingService->findExercise($exercise);

        if ($model === null) {
            return ApiResponse::error('Exercise not found', 404);
        }

        return ApiResponse::success(
            data: (new ExerciseResource($model))->resolve(),
            message: 'Exercise retrieved',
        );
    }

    public function bodybuilding(Request $request): JsonResponse
    {
        $request->merge(['type' => 'bodybuilding']);

        return $this->exercises($request);
    }

    public function mma(Request $request): JsonResponse
    {
        $request->merge(['type' => 'mma']);

        return $this->exercises($request);
    }

    public function homeCalisthenics(Request $request): JsonResponse
    {
        $request->merge(['type' => 'home_calisthenics']);

        return $this->exercises($request);
    }

    public function sessions(Request $request): JsonResponse
    {
        $paginator = $this->trainingService->listSessions($request->user(), (int) $request->query('per_page', 20));

        return ApiResponse::success(
            data: [
                'items' => WorkoutSessionResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Workout sessions retrieved',
        );
    }

    public function startSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'program_id' => ['nullable', 'integer', 'exists:training_programs,id'],
            'exercise_ids' => ['nullable', 'array', 'min:1'],
            'exercise_ids.*' => ['integer', 'exists:exercises,id'],
        ]);

        $session = $this->trainingService->startSession($request->user(), $validated);

        return ApiResponse::success(
            data: (new WorkoutSessionResource($session))->resolve(),
            message: 'Workout session started',
            status: 201,
        );
    }

    public function showSession(Request $request, int $session): JsonResponse
    {
        $model = $this->trainingService->loadSession($session, $request->user()->id);
        if ($model === null) {
            return ApiResponse::error('Session not found', 404);
        }

        return ApiResponse::success(
            data: (new WorkoutSessionResource($model))->resolve(),
            message: 'Workout session retrieved',
        );
    }

    public function pauseSession(Request $request, int $session): JsonResponse
    {
        $model = $this->ownedSession($request, $session);
        if ($model === null) {
            return ApiResponse::error('Session not found', 404);
        }

        $updated = $this->trainingService->pauseSession($model);

        return ApiResponse::success(
            data: (new WorkoutSessionResource($updated))->resolve(),
            message: 'Workout paused',
        );
    }

    public function resumeSession(Request $request, int $session): JsonResponse
    {
        $model = $this->ownedSession($request, $session);
        if ($model === null) {
            return ApiResponse::error('Session not found', 404);
        }

        $updated = $this->trainingService->resumeSession($model);

        return ApiResponse::success(
            data: (new WorkoutSessionResource($updated))->resolve(),
            message: 'Workout resumed',
        );
    }

    public function completeSet(Request $request, int $session): JsonResponse
    {
        $model = $this->ownedSession($request, $session);
        if ($model === null) {
            return ApiResponse::error('Session not found', 404);
        }

        $validated = $request->validate([
            'session_exercise_id' => ['required', 'integer'],
            'set_number' => ['nullable', 'integer', 'min:1'],
            'reps' => ['nullable', 'integer', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_warmup' => ['nullable', 'boolean'],
        ]);

        $updated = $this->trainingService->completeSet($model, $validated);

        return ApiResponse::success(
            data: (new WorkoutSessionResource($updated))->resolve(),
            message: 'Set completed',
        );
    }

    public function completeSession(Request $request, int $session): JsonResponse
    {
        $model = $this->ownedSession($request, $session);
        if ($model === null) {
            return ApiResponse::error('Session not found', 404);
        }

        $updated = $this->trainingService->completeSession($model);

        return ApiResponse::success(
            data: (new WorkoutSessionResource($updated))->resolve(),
            message: 'Workout completed',
        );
    }

    public function cancelSession(Request $request, int $session): JsonResponse
    {
        $model = $this->ownedSession($request, $session);
        if ($model === null) {
            return ApiResponse::error('Session not found', 404);
        }

        $updated = $this->trainingService->cancelSession($model);

        return ApiResponse::success(
            data: (new WorkoutSessionResource($updated))->resolve(),
            message: 'Workout cancelled',
        );
    }

    private function ownedSession(Request $request, int $sessionId): ?WorkoutSession
    {
        return WorkoutSession::query()
            ->forUser($request->user()->id)
            ->find($sessionId);
    }
}
