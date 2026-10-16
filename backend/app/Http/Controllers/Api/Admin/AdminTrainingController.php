<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExerciseRequest;
use App\Http\Requests\Admin\StoreExerciseVideoRequest;
use App\Http\Requests\Admin\StoreTrainingCategoryRequest;
use App\Http\Requests\Admin\UpdateExerciseRequest;
use App\Http\Requests\Admin\UpdateExerciseVideoRequest;
use App\Http\Requests\Admin\UpdateTrainingCategoryRequest;
use App\Models\Exercise;
use App\Models\ExerciseVideo;
use App\Models\TrainingCategory;
use App\Services\Admin\AdminTrainingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTrainingController extends Controller
{
    public function __construct(
        private readonly AdminTrainingService $trainingService,
    ) {}

    public function categories(): JsonResponse
    {
        $items = $this->trainingService->listCategories()->map(fn (TrainingCategory $c) => [
            'id' => $c->id,
            'name_en' => $c->name_en,
            'name_ar' => $c->name_ar,
            'slug' => $c->slug,
            'type' => $c->type,
            'level' => $c->level,
            'is_active' => (bool) $c->is_active,
            'sort_order' => $c->sort_order,
        ]);

        return ApiResponse::success(data: ['items' => $items], message: 'Training categories');
    }

    public function storeCategory(StoreTrainingCategoryRequest $request): JsonResponse
    {
        $category = $this->trainingService->createCategory($request->validated());

        return ApiResponse::success(data: $category->toArray(), message: 'Category created', status: 201);
    }

    public function updateCategory(UpdateTrainingCategoryRequest $request, TrainingCategory $category): JsonResponse
    {
        $updated = $this->trainingService->updateCategory($category, $request->validated());

        return ApiResponse::success(data: $updated->toArray(), message: 'Category updated');
    }

    public function destroyCategory(TrainingCategory $category): JsonResponse
    {
        $this->trainingService->deleteCategory($category);

        return ApiResponse::success(message: 'Category deleted');
    }

    public function exercises(Request $request): JsonResponse
    {
        $paginator = $this->trainingService->listExercises(
            $request->query('search'),
            $request->query('category_id') !== null ? (int) $request->query('category_id') : null,
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => collect($paginator->items())->map(fn (Exercise $e) => $this->exercisePayload($e)),
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

    public function storeExercise(StoreExerciseRequest $request): JsonResponse
    {
        $exercise = $this->trainingService->createExercise($request->validated());

        return ApiResponse::success(data: $this->exercisePayload($exercise), message: 'Exercise created', status: 201);
    }

    public function updateExercise(UpdateExerciseRequest $request, Exercise $exercise): JsonResponse
    {
        $updated = $this->trainingService->updateExercise($exercise, $request->validated());

        return ApiResponse::success(data: $this->exercisePayload($updated), message: 'Exercise updated');
    }

    public function destroyExercise(Exercise $exercise): JsonResponse
    {
        $this->trainingService->deleteExercise($exercise);

        return ApiResponse::success(message: 'Exercise deleted');
    }

    public function storeVideo(StoreExerciseVideoRequest $request, Exercise $exercise): JsonResponse
    {
        $video = $this->trainingService->createVideo($exercise, $request->validated());

        return ApiResponse::success(data: $video->toArray(), message: 'Video created', status: 201);
    }

    public function updateVideo(UpdateExerciseVideoRequest $request, ExerciseVideo $video): JsonResponse
    {
        $updated = $this->trainingService->updateVideo($video, $request->validated());

        return ApiResponse::success(data: $updated->toArray(), message: 'Video updated');
    }

    public function destroyVideo(ExerciseVideo $video): JsonResponse
    {
        $this->trainingService->deleteVideo($video);

        return ApiResponse::success(message: 'Video deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function exercisePayload(Exercise $e): array
    {
        return [
            'id' => $e->id,
            'training_category_id' => $e->training_category_id,
            'name_en' => $e->name_en,
            'name_ar' => $e->name_ar,
            'difficulty' => $e->difficulty,
            'equipment' => $e->equipment,
            'primary_muscles' => $e->primary_muscles ?? [],
            'secondary_muscles' => $e->secondary_muscles ?? [],
            'metric_type' => $e->metric_type ?? 'reps',
            'sets' => $e->sets,
            'reps_min' => $e->reps_min,
            'reps_max' => $e->reps_max,
            'duration_seconds' => $e->duration_seconds,
            'rest_seconds' => $e->rest_seconds,
            'rounds' => $e->rounds,
            'round_seconds' => $e->round_seconds,
            'round_rest_seconds' => $e->round_rest_seconds,
            'is_active' => (bool) $e->is_active,
            'sort_order' => $e->sort_order,
            'thumbnail_url' => $e->thumbnail_url,
            'category' => $e->relationLoaded('category') && $e->category
                ? [
                    'id' => $e->category->id,
                    'name_en' => $e->category->name_en,
                    'slug' => $e->category->slug,
                    'type' => $e->category->type,
                    'level' => $e->category->level,
                ]
                : null,
        ];
    }
}
