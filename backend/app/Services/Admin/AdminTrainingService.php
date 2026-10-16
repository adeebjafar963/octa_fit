<?php

namespace App\Services\Admin;

use App\Models\Exercise;
use App\Models\ExerciseVideo;
use App\Models\TrainingCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AdminTrainingService
{
    public function listCategories(): \Illuminate\Support\Collection
    {
        return TrainingCategory::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function createCategory(array $data): TrainingCategory
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name_en']);

        return TrainingCategory::query()->create($data);
    }

    public function updateCategory(TrainingCategory $category, array $data): TrainingCategory
    {
        if (isset($data['name_en']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name_en']);
        }
        $category->update($data);

        return $category->fresh();
    }

    public function deleteCategory(TrainingCategory $category): void
    {
        $category->delete();
    }

    public function listExercises(?string $search = null, ?int $categoryId = null, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return Exercise::query()
            ->with('category')
            ->when($categoryId, fn ($q) => $q->where('training_category_id', $categoryId))
            ->search($search)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createExercise(array $data): Exercise
    {
        return Exercise::query()->create($data)->load('category');
    }

    public function updateExercise(Exercise $exercise, array $data): Exercise
    {
        $exercise->update($data);

        return $exercise->fresh(['category', 'videos']);
    }

    public function deleteExercise(Exercise $exercise): void
    {
        $exercise->delete();
    }

    public function createVideo(Exercise $exercise, array $data): ExerciseVideo
    {
        $data['exercise_id'] = $exercise->id;

        return ExerciseVideo::query()->create($data);
    }

    public function updateVideo(ExerciseVideo $video, array $data): ExerciseVideo
    {
        $video->update($data);

        return $video->fresh();
    }

    public function deleteVideo(ExerciseVideo $video): void
    {
        $video->delete();
    }
}
