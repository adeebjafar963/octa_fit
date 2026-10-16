<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\TrainingCategory;
use App\Models\TrainingProgram;
use App\Models\TrainingProgramExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainingService
{
    /** @var list<string> */
    public const DISCIPLINES = ['bodybuilding', 'mma', 'home_calisthenics'];

    /** @var list<string> */
    public const LEVELS = ['beginner', 'intermediate', 'advanced'];

    /**
     * @return list<array<string, mixed>>
     */
    public function listDisciplines(): array
    {
        $meta = [
            'bodybuilding' => [
                'slug' => 'bodybuilding',
                'name_en' => 'Bodybuilding',
                'name_ar' => 'كمال الأجسام',
                'description_en' => 'Hypertrophy and strength training by body part.',
                'description_ar' => 'تدريب القوة وبناء العضلات حسب أجزاء الجسم.',
                'accent' => 'teal',
            ],
            'mma' => [
                'slug' => 'mma',
                'name_en' => 'MMA',
                'name_ar' => 'فنون القتال المختلطة',
                'description_en' => 'Striking, grappling, defense, and conditioning.',
                'description_ar' => 'الضربات والمصارعة والدفاع واللياقة.',
                'accent' => 'brick',
            ],
            'home_calisthenics' => [
                'slug' => 'home_calisthenics',
                'name_en' => 'Home Workout & Calisthenics',
                'name_ar' => 'التمارين المنزلية والكاليستنيكس',
                'description_en' => 'Bodyweight training you can do anywhere.',
                'description_ar' => 'تدريب بوزن الجسم في أي مكان.',
                'accent' => 'primary',
            ],
        ];

        $counts = TrainingCategory::query()
            ->active()
            ->selectRaw('type, count(*) as categories_count')
            ->groupBy('type')
            ->pluck('categories_count', 'type');

        $exerciseCounts = Exercise::query()
            ->where('exercises.is_active', true)
            ->whereHas('category', fn ($q) => $q->active())
            ->join('training_categories', 'exercises.training_category_id', '=', 'training_categories.id')
            ->selectRaw('training_categories.type as type, count(exercises.id) as exercises_count')
            ->groupBy('training_categories.type')
            ->pluck('exercises_count', 'type');

        $out = [];
        foreach (self::DISCIPLINES as $slug) {
            $row = $meta[$slug];
            $row['categories_count'] = (int) ($counts[$slug] ?? 0);
            $row['exercises_count'] = (int) ($exerciseCounts[$slug] ?? 0);
            $row['levels'] = self::LEVELS;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return Collection<int, TrainingCategory>
     */
    public function listCategories(?string $type = null, ?string $level = null): Collection
    {
        $query = TrainingCategory::query()
            ->active()
            ->withCount(['exercises as exercises_count' => function ($q) use ($level) {
                $q->active();
                if ($level !== null && $level !== '' && $level !== 'all') {
                    $q->where('difficulty', $level);
                }
            }])
            ->orderBy('sort_order');

        if ($type !== null && $type !== '') {
            $query->ofType($type);
        }

        if ($level !== null && $level !== '' && $level !== 'all') {
            $query->whereHas('exercises', function ($q) use ($level) {
                $q->active()->where('difficulty', $level);
            });
        }

        return $query->get();
    }

    public function findCategory(int|string $idOrSlug): ?TrainingCategory
    {
        $query = TrainingCategory::query()->active();

        if (is_numeric($idOrSlug)) {
            return $query->where('id', (int) $idOrSlug)->first();
        }

        return $query->where('slug', $idOrSlug)->first();
    }

    /**
     * @return Collection<int, TrainingProgram>
     */
    public function listPrograms(?string $type = null, ?string $level = null, ?int $categoryId = null): Collection
    {
        $query = TrainingProgram::query()
            ->active()
            ->withCount('programExercises')
            ->with(['category'])
            ->orderBy('sort_order');

        if ($type) {
            $query->where('type', $type);
        }
        if ($level) {
            $query->where('level', $level);
        }
        if ($categoryId) {
            $query->where('training_category_id', $categoryId);
        }

        return $query->get();
    }

    public function findProgram(int|string $idOrSlug): ?TrainingProgram
    {
        $query = TrainingProgram::query()
            ->active()
            ->with([
                'category',
                'programExercises' => fn ($q) => $q->orderBy('sort_order')->with([
                    'exercise' => fn ($eq) => $eq->active()->with(['videos' => fn ($vq) => $vq->active()]),
                ]),
            ]);

        if (is_numeric($idOrSlug)) {
            return $query->where('id', (int) $idOrSlug)->first();
        }

        return $query->where('slug', $idOrSlug)->first();
    }

    public function listExercises(array $filters = []): LengthAwarePaginator
    {
        $query = Exercise::query()
            ->active()
            ->with(['category', 'videos' => fn ($q) => $q->active()])
            ->orderBy('sort_order');

        if (! empty($filters['type'])) {
            $query->whereHas('category', function ($q) use ($filters) {
                $q->active()->ofType($filters['type']);
            });
        }

        if (! empty($filters['level'])) {
            $query->where('difficulty', $filters['level']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('training_category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['category'])) {
            $slugOrId = $filters['category'];
            $query->whereHas('category', function ($q) use ($slugOrId) {
                $q->active();
                if (is_numeric($slugOrId)) {
                    $q->where('id', (int) $slugOrId);
                } else {
                    $q->where('slug', $slugOrId);
                }
            });
        }

        if (! empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }

        if (! empty($filters['equipment'])) {
            $query->where('equipment', $filters['equipment']);
        }

        if (! empty($filters['exercise_type'])) {
            $query->where('exercise_type', $filters['exercise_type']);
        }

        if (array_key_exists('home_friendly', $filters) && $filters['home_friendly'] !== null && $filters['home_friendly'] !== '') {
            $query->where('home_friendly', filter_var($filters['home_friendly'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('gym_friendly', $filters) && $filters['gym_friendly'] !== null && $filters['gym_friendly'] !== '') {
            $query->where('gym_friendly', filter_var($filters['gym_friendly'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 50);

        return $query->paginate($perPage);
    }

    public function findExercise(int $id): ?Exercise
    {
        return Exercise::query()
            ->active()
            ->with([
                'category',
                'videos' => fn ($q) => $q->active()->orderBy('sort_order'),
                'alternatives' => fn ($q) => $q->active()->with(['category', 'videos' => fn ($vq) => $vq->active()]),
                'progression',
                'regression',
            ])
            ->find($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function favoriteIdsForUser(User $user): array
    {
        return $user->belongsToMany(Exercise::class, 'exercise_favorites')
            ->pluck('exercises.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function toggleFavorite(User $user, int $exerciseId): bool
    {
        $exercise = Exercise::query()->active()->find($exerciseId);
        if ($exercise === null) {
            throw ValidationException::withMessages(['exercise_id' => 'Exercise not found.']);
        }

        $exists = $user->belongsToMany(Exercise::class, 'exercise_favorites')
            ->where('exercise_id', $exerciseId)
            ->exists();

        if ($exists) {
            $user->belongsToMany(Exercise::class, 'exercise_favorites')->detach($exerciseId);

            return false;
        }

        $user->belongsToMany(Exercise::class, 'exercise_favorites')->attach($exerciseId);

        return true;
    }

    public function startSession(User $user, array $payload): WorkoutSession
    {
        return DB::transaction(function () use ($user, $payload) {
            $program = null;
            $exercises = [];

            if (! empty($payload['program_id'])) {
                $program = $this->findProgram((int) $payload['program_id']);
                if ($program === null) {
                    throw ValidationException::withMessages(['program_id' => 'Program not found.']);
                }
                foreach ($program->programExercises as $pe) {
                    if ($pe->exercise === null) {
                        continue;
                    }
                    $exercises[] = [
                        'exercise' => $pe->exercise,
                        'program_exercise_id' => $pe->id,
                        'target_sets' => $pe->target_sets ?? $pe->exercise->sets,
                        'target_reps_min' => $pe->target_reps_min ?? $pe->exercise->reps_min,
                        'target_reps_max' => $pe->target_reps_max ?? $pe->exercise->reps_max,
                        'target_duration_seconds' => $pe->target_duration_seconds ?? $pe->exercise->duration_seconds,
                        'rest_seconds' => $pe->rest_seconds ?? $pe->exercise->rest_seconds,
                        'rounds' => $pe->rounds ?? $pe->exercise->rounds,
                        'round_seconds' => $pe->round_seconds ?? $pe->exercise->round_seconds,
                        'round_rest_seconds' => $pe->round_rest_seconds ?? $pe->exercise->round_rest_seconds,
                    ];
                }
            } elseif (! empty($payload['exercise_ids']) && is_array($payload['exercise_ids'])) {
                $ids = array_values(array_unique(array_map('intval', $payload['exercise_ids'])));
                $found = Exercise::query()->active()->whereIn('id', $ids)->with('category')->get()->keyBy('id');
                foreach ($ids as $id) {
                    $ex = $found->get($id);
                    if ($ex === null) {
                        continue;
                    }
                    $exercises[] = [
                        'exercise' => $ex,
                        'program_exercise_id' => null,
                        'target_sets' => $ex->sets,
                        'target_reps_min' => $ex->reps_min,
                        'target_reps_max' => $ex->reps_max,
                        'target_duration_seconds' => $ex->duration_seconds,
                        'rest_seconds' => $ex->rest_seconds,
                        'rounds' => $ex->rounds,
                        'round_seconds' => $ex->round_seconds,
                        'round_rest_seconds' => $ex->round_rest_seconds,
                    ];
                }
            }

            if ($exercises === []) {
                throw ValidationException::withMessages(['exercise_ids' => 'At least one exercise is required.']);
            }

            $first = $exercises[0]['exercise'];
            $type = $program?->type ?? $first->category?->type;
            $level = $program?->level ?? $first->difficulty ?? $first->category?->level;

            $session = WorkoutSession::query()->create([
                'user_id' => $user->id,
                'training_program_id' => $program?->id,
                'type' => $type,
                'level' => $level,
                'title_en' => $program?->name_en ?? $first->name_en,
                'title_ar' => $program?->name_ar ?? $first->name_ar,
                'status' => 'active',
                'started_at' => now(),
            ]);

            foreach ($exercises as $i => $row) {
                WorkoutSessionExercise::query()->create([
                    'workout_session_id' => $session->id,
                    'exercise_id' => $row['exercise']->id,
                    'training_program_exercise_id' => $row['program_exercise_id'],
                    'sort_order' => $i + 1,
                    'status' => $i === 0 ? 'active' : 'pending',
                    'target_sets' => $row['target_sets'],
                    'target_reps_min' => $row['target_reps_min'],
                    'target_reps_max' => $row['target_reps_max'],
                    'target_duration_seconds' => $row['target_duration_seconds'],
                    'rest_seconds' => $row['rest_seconds'],
                    'rounds' => $row['rounds'],
                    'round_seconds' => $row['round_seconds'],
                    'round_rest_seconds' => $row['round_rest_seconds'],
                    'started_at' => $i === 0 ? now() : null,
                ]);
            }

            return $this->loadSession($session->id, $user->id);
        });
    }

    public function loadSession(int $sessionId, int $userId): ?WorkoutSession
    {
        return WorkoutSession::query()
            ->forUser($userId)
            ->with([
                'program',
                'sessionExercises' => fn ($q) => $q->orderBy('sort_order')->with([
                    'exercise.videos',
                    'exercise.category',
                    'sets',
                ]),
            ])
            ->find($sessionId);
    }

    public function listSessions(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return WorkoutSession::query()
            ->forUser($user->id)
            ->with(['program'])
            ->orderByDesc('started_at')
            ->paginate(min(max($perPage, 1), 50));
    }

    public function pauseSession(WorkoutSession $session): WorkoutSession
    {
        if ($session->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'Only active sessions can be paused.']);
        }

        $session->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        return $this->loadSession($session->id, $session->user_id);
    }

    public function resumeSession(WorkoutSession $session): WorkoutSession
    {
        if ($session->status !== 'paused') {
            throw ValidationException::withMessages(['status' => 'Only paused sessions can be resumed.']);
        }

        $extra = (int) $session->paused_total_seconds;
        if ($session->paused_at !== null) {
            $extra += max(0, now()->getTimestamp() - $session->paused_at->getTimestamp());
        }

        $session->update([
            'status' => 'active',
            'paused_at' => null,
            'paused_total_seconds' => $extra,
        ]);

        return $this->loadSession($session->id, $session->user_id);
    }

    public function completeSet(WorkoutSession $session, array $payload): WorkoutSession
    {
        if (! in_array($session->status, ['active', 'paused'], true)) {
            throw ValidationException::withMessages(['status' => 'Session is not editable.']);
        }

        $sessionExercise = WorkoutSessionExercise::query()
            ->where('workout_session_id', $session->id)
            ->where('id', (int) $payload['session_exercise_id'])
            ->first();

        if ($sessionExercise === null) {
            throw ValidationException::withMessages(['session_exercise_id' => 'Exercise not in session.']);
        }

        $setNumber = (int) ($payload['set_number'] ?? ($sessionExercise->sets()->count() + 1));

        WorkoutSet::query()->create([
            'workout_session_exercise_id' => $sessionExercise->id,
            'set_number' => $setNumber,
            'reps' => isset($payload['reps']) ? (int) $payload['reps'] : null,
            'weight_kg' => isset($payload['weight_kg']) ? (float) $payload['weight_kg'] : null,
            'duration_seconds' => isset($payload['duration_seconds']) ? (int) $payload['duration_seconds'] : null,
            'is_warmup' => (bool) ($payload['is_warmup'] ?? false),
            'completed_at' => now(),
        ]);

        if ($sessionExercise->status === 'pending') {
            $sessionExercise->update(['status' => 'active', 'started_at' => $sessionExercise->started_at ?? now()]);
        }

        $setsDone = $sessionExercise->sets()->count();
        $target = (int) ($sessionExercise->target_sets ?? $sessionExercise->rounds ?? 1);
        if ($setsDone >= $target) {
            $sessionExercise->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $next = WorkoutSessionExercise::query()
                ->where('workout_session_id', $session->id)
                ->where('status', 'pending')
                ->orderBy('sort_order')
                ->first();
            if ($next !== null) {
                $next->update(['status' => 'active', 'started_at' => now()]);
            }
        }

        $this->refreshSessionAggregates($session);

        return $this->loadSession($session->id, $session->user_id);
    }

    public function completeSession(WorkoutSession $session): WorkoutSession
    {
        if (in_array($session->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Session already finished.']);
        }

        if ($session->status === 'paused' && $session->paused_at !== null) {
            $extra = (int) $session->paused_total_seconds
                + max(0, now()->getTimestamp() - $session->paused_at->getTimestamp());
            $session->paused_total_seconds = $extra;
            $session->paused_at = null;
        }

        $duration = $session->activeElapsedSeconds();

        WorkoutSessionExercise::query()
            ->where('workout_session_id', $session->id)
            ->whereIn('status', ['pending', 'active'])
            ->update(['status' => 'skipped']);

        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_seconds' => $duration,
            'paused_at' => null,
        ]);

        $this->refreshSessionAggregates($session->fresh());

        return $this->loadSession($session->id, $session->user_id);
    }

    public function cancelSession(WorkoutSession $session): WorkoutSession
    {
        if (in_array($session->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Session already finished.']);
        }

        $session->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'duration_seconds' => $session->activeElapsedSeconds(),
            'paused_at' => null,
        ]);

        return $this->loadSession($session->id, $session->user_id);
    }

    private function refreshSessionAggregates(WorkoutSession $session): void
    {
        $exercisesCompleted = WorkoutSessionExercise::query()
            ->where('workout_session_id', $session->id)
            ->where('status', 'completed')
            ->count();

        $sets = WorkoutSet::query()
            ->whereHas('sessionExercise', fn ($q) => $q->where('workout_session_id', $session->id))
            ->get();

        $session->update([
            'exercises_completed' => $exercisesCompleted,
            'sets_completed' => $sets->count(),
            'total_reps' => (int) $sets->sum(fn ($s) => (int) ($s->reps ?? 0)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function trainingContextForUser(User $user): array
    {
        $recent = WorkoutSession::query()
            ->forUser($user->id)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get([
                'id', 'type', 'level', 'title_en', 'title_ar',
                'duration_seconds', 'exercises_completed', 'sets_completed',
                'total_reps', 'completed_at',
            ]);

        $active = WorkoutSession::query()
            ->forUser($user->id)
            ->whereIn('status', ['active', 'paused'])
            ->orderByDesc('started_at')
            ->first(['id', 'type', 'level', 'title_en', 'status', 'started_at']);

        $recentPayload = $recent->map(fn (WorkoutSession $s) => [
            'id' => $s->id,
            'type' => $s->type,
            'level' => $s->level,
            'title_en' => $s->title_en,
            'title_ar' => $s->title_ar,
            'duration_seconds' => $s->duration_seconds,
            'exercises_completed' => $s->exercises_completed,
            'sets_completed' => $s->sets_completed,
            'total_reps' => $s->total_reps,
            'completed_at' => optional($s->completed_at)?->toIso8601String(),
        ])->values()->all();

        return [
            'recent_workouts' => $recentPayload,
            'active_session' => $active === null ? null : [
                'id' => $active->id,
                'type' => $active->type,
                'level' => $active->level,
                'title_en' => $active->title_en,
                'status' => $active->status,
                'started_at' => optional($active->started_at)?->toIso8601String(),
            ],
            'completed_count_30d' => WorkoutSession::query()
                ->forUser($user->id)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays(30))
                ->count(),
            // Phase 11A — lightweight recovery signals for future planning engines.
            'recent_training_types' => $recent->pluck('type')->filter()->unique()->values()->all(),
            'last_completed_at' => optional($recent->first()?->completed_at)?->toIso8601String(),
        ];
    }
}
