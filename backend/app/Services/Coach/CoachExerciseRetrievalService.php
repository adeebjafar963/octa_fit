<?php

namespace App\Services\Coach;

use App\Models\Exercise;
use App\Models\User;
use App\Services\TrainingService;
use Illuminate\Support\Facades\Log;

/**
 * Phase 11B — deterministic OCTA FIT exercise retrieval + ID grounding.
 *
 * Single source of truth: existing `exercises` table via TrainingService rules.
 * Never invents IDs. Never creates exercises.
 */
class CoachExerciseRetrievalService
{
    public const MAX_CANDIDATES = 12;

    public function __construct(
        private readonly TrainingService $trainingService,
    ) {}

    /**
     * Retrieve grounded exercise candidates for a coach turn.
     *
     * @param  array<string, mixed>  $userContext  Phase 11A CoachUserContext payload
     * @return list<array<string, mixed>>
     */
    public function retrieveForMessage(string $message, array $userContext = [], ?string $language = null): array
    {
        $filters = $this->buildFilters($message, $userContext);
        $exercises = $this->queryActiveExercises($filters);

        return $this->toGroundedList($exercises, $language ?? ($userContext['locale'] ?? 'en'));
    }

    /**
     * Validate untrusted exercise IDs (e.g. from an AI provider).
     *
     * @param  list<int|string>  $ids
     * @return array{valid: list<array<string, mixed>>, rejected: list<array{id: int|string, reason: string}>}
     */
    public function validateIds(array $ids, ?string $language = 'en'): array
    {
        $valid = [];
        $rejected = [];
        $seen = [];

        foreach ($ids as $rawId) {
            if (! is_numeric($rawId)) {
                $rejected[] = ['id' => $rawId, 'reason' => 'not_numeric'];
                Log::debug('coach.exercise_grounding.rejected', ['id' => $rawId, 'reason' => 'not_numeric']);

                continue;
            }

            $id = (int) $rawId;
            if ($id <= 0) {
                $rejected[] = ['id' => $rawId, 'reason' => 'invalid_id'];
                Log::debug('coach.exercise_grounding.rejected', ['id' => $id, 'reason' => 'invalid_id']);

                continue;
            }

            if (isset($seen[$id])) {
                continue; // dedupe by stable DB id
            }
            $seen[$id] = true;

            $exercise = $this->trainingService->findExercise($id);
            if ($exercise === null) {
                $rejected[] = ['id' => $id, 'reason' => 'not_found_or_inactive'];
                Log::debug('coach.exercise_grounding.rejected', [
                    'id' => $id,
                    'reason' => 'not_found_or_inactive',
                ]);

                continue;
            }

            $valid[] = $this->toGrounded($exercise, $language);
        }

        return ['valid' => $valid, 'rejected' => $rejected];
    }

    /**
     * Validate a single ID — returns grounded payload or null.
     *
     * @return array<string, mixed>|null
     */
    public function validateOne(int $id, ?string $language = 'en'): ?array
    {
        $result = $this->validateIds([$id], $language);

        return $result['valid'][0] ?? null;
    }

    /**
     * Phase 11C — retrieve grounded exercises with explicit filters (batch plan selection).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function retrieveByFilters(array $filters, ?string $language = 'en'): array
    {
        $filters['per_page'] = min(
            max((int) ($filters['per_page'] ?? 40), 1),
            50,
        );
        $exercises = $this->queryActiveExercises($filters);

        return $this->toGroundedList($exercises, $language);
    }

    /**
     * Build listExercises-compatible filters from message + user context.
     * Only uses columns that already exist on exercises / categories.
     *
     * @param  array<string, mixed>  $userContext
     * @return array<string, mixed>
     */
    public function buildFilters(string $message, array $userContext = []): array
    {
        $lower = mb_strtolower($message);
        $filters = [
            'per_page' => self::MAX_CANDIDATES,
        ];

        $discipline = $this->detectDiscipline($lower, $userContext);
        if ($discipline !== null) {
            $filters['type'] = $discipline;
        }

        $level = $this->detectLevel($lower, $userContext);
        if ($level !== null) {
            $filters['level'] = $level;
        }

        $search = $this->detectSearchTerm($lower, $message);
        if ($search !== null) {
            $filters['search'] = $search;
        }

        $equipment = $this->detectEquipmentFilter($lower, $userContext);
        if ($equipment !== null) {
            $filters['equipment'] = $equipment;
        }

        if ($this->wantsHome($lower, $userContext)) {
            $filters['home_friendly'] = true;
        }

        // Muscle hint uses existing primary_muscles JSON / target / names — not a public API filter.
        // Prefer explicit semantic body_part from NLU (scope control) over message heuristics.
        $semanticPart = $userContext['requested_body_part']
            ?? $userContext['body_part']
            ?? null;
        $muscle = is_string($semanticPart) && $semanticPart !== ''
            ? $this->mapBodyPartToMuscle($semanticPart)
            : $this->detectMuscle($lower);
        if ($muscle !== null) {
            $filters['muscle'] = $muscle;
        }

        $semanticLevel = $userContext['requested_level'] ?? null;
        if (is_string($semanticLevel) && $semanticLevel !== '' && empty($filters['level'])) {
            $filters['level'] = $semanticLevel;
        }

        return $filters;
    }

    private function mapBodyPartToMuscle(string $bodyPart): ?string
    {
        return match (mb_strtolower(trim($bodyPart))) {
            'shoulders', 'shoulder' => 'shoulders',
            'chest' => 'chest',
            'back' => 'back',
            'legs', 'leg' => 'quadriceps',
            'arms', 'biceps', 'bicep' => 'biceps',
            'triceps', 'tricep' => 'triceps',
            'abs', 'core' => 'core',
            'glutes', 'glute' => 'glutes',
            default => $this->detectMuscle(mb_strtolower($bodyPart)),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<Exercise>
     */
    private function queryActiveExercises(array $filters): array
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

        if (! empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
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

        if (! empty($filters['muscle'])) {
            $muscle = (string) $filters['muscle'];
            $query->where(function ($q) use ($muscle) {
                $q->whereJsonContains('primary_muscles', $muscle)
                    ->orWhere('target', 'like', '%'.$muscle.'%')
                    ->orWhere('name_en', 'like', '%'.$muscle.'%')
                    ->orWhere('name_ar', 'like', '%'.$muscle.'%');
            });
        }

        if (! empty($filters['movement_pattern'])) {
            $query->where('movement_pattern', $filters['movement_pattern']);
        }

        if (! empty($filters['exclude_ids']) && is_array($filters['exclude_ids'])) {
            $exclude = array_values(array_filter(array_map('intval', $filters['exclude_ids'])));
            if ($exclude !== []) {
                $query->whereNotIn('id', $exclude);
            }
        }

        $limit = min(max((int) ($filters['per_page'] ?? self::MAX_CANDIDATES), 1), 50);

        return $query->limit($limit)->get()->all();
    }

    /**
     * @param  list<Exercise>  $exercises
     * @return list<array<string, mixed>>
     */
    private function toGroundedList(array $exercises, ?string $language): array
    {
        $out = [];
        $seen = [];
        foreach ($exercises as $exercise) {
            if (! $exercise instanceof Exercise) {
                continue;
            }
            if (isset($seen[$exercise->id])) {
                continue;
            }
            $seen[$exercise->id] = true;
            $out[] = $this->toGrounded($exercise, $language);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function toGrounded(Exercise $exercise, ?string $language = 'en'): array
    {
        $lang = $language === 'ar' ? 'ar' : 'en';
        $name = $lang === 'ar'
            ? ($exercise->name_ar ?: $exercise->name_en)
            : ($exercise->name_en ?: $exercise->name_ar);
        $category = $exercise->relationLoaded('category') ? $exercise->category : null;
        $hasMedia = $exercise->relationLoaded('videos')
            ? $exercise->videos->contains(fn ($v) => (bool) $v->is_active)
            : false;

        return [
            'exercise_id' => (int) $exercise->id,
            'name' => $name,
            'name_en' => $exercise->name_en,
            'name_ar' => $exercise->name_ar,
            'slug' => $exercise->slug,
            'discipline' => $category?->type,
            'level' => $exercise->difficulty,
            'category' => $category?->slug,
            'category_id' => $exercise->training_category_id,
            'equipment' => $exercise->equipment,
            'primary_muscles' => $exercise->primary_muscles ?? [],
            'secondary_muscles' => $exercise->secondary_muscles ?? [],
            'exercise_type' => $exercise->exercise_type,
            'movement_pattern' => $exercise->movement_pattern,
            'has_media' => $hasMedia,
            'is_active' => (bool) $exercise->is_active,
            'grounded' => true,
            // Flutter deep link contract (existing route — no new screen).
            'flutter_route' => '/training/exercises/'.$exercise->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $userContext
     */
    private function detectDiscipline(string $lower, array $userContext): ?string
    {
        if (preg_match('/\b(mma|kick|striking|grappling|sparring|فنون القتال|قتال)\b/u', $lower)) {
            return 'mma';
        }
        if (preg_match('/\b(home|bodyweight|calisthenics|push-?up|pull-?up|منزلي|كالستنيك|وزن الجسم)\b/u', $lower)) {
            return 'home_calisthenics';
        }
        if (preg_match('/\b(bodybuilding|hypertrophy|gym|bulk|bench|squat|كمال الأجسام|تضخيم|صالة)\b/u', $lower)) {
            return 'bodybuilding';
        }

        $types = $userContext['workout']['recent_training_types'] ?? [];
        if (is_array($types) && count($types) === 1 && in_array($types[0], TrainingService::DISCIPLINES, true)) {
            return $types[0];
        }

        $location = $userContext['training']['training_location']
            ?? $userContext['training_location']
            ?? null;
        if ($location === 'home') {
            return 'home_calisthenics';
        }

        $goal = $userContext['goals']['primary_goal'] ?? $userContext['primary_goal'] ?? null;
        if ($goal === 'mma') {
            return 'mma';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $userContext
     */
    private function detectLevel(string $lower, array $userContext): ?string
    {
        if (preg_match('/\b(beginner|مبتدئ)\b/u', $lower)) {
            return 'beginner';
        }
        if (preg_match('/\b(advanced|متقدم)\b/u', $lower)) {
            return 'advanced';
        }
        if (preg_match('/\b(intermediate|متوسط)\b/u', $lower)) {
            return 'intermediate';
        }

        $level = $userContext['training']['fitness_level']
            ?? $userContext['fitness_level']
            ?? null;

        return in_array($level, TrainingService::LEVELS, true) ? $level : null;
    }

    private function detectSearchTerm(string $lower, string $original): ?string
    {
        // Prefer concrete exercise / body-part cues for DB search.
        $cues = [
            'bench press' => 'bench press',
            'squat' => 'squat',
            'deadlift' => 'deadlift',
            'pull-up' => 'pull-up',
            'pull up' => 'pull-up',
            'push-up' => 'push-up',
            'push up' => 'push-up',
            'jab' => 'jab',
            'uppercut' => 'uppercut',
            'kick' => 'kick',
            'chest' => 'chest',
            'back' => 'back',
            'shoulder' => 'shoulder',
            'leg' => 'leg',
            'bicep' => 'bicep',
            'tricep' => 'tricep',
            'core' => 'core',
            'صدر' => 'صدر',
            'ظهر' => 'ظهر',
            'كتف' => 'كتف',
            'رجل' => 'رجل',
            'ضغط' => 'ضغط',
        ];

        foreach ($cues as $needle => $term) {
            if (str_contains($lower, $needle)) {
                return $term;
            }
        }

        return null;
    }

    private function detectMuscle(string $lower): ?string
    {
        // Prefer longer Arabic tokens first so أكتاف/اكتاف beat bare كتف substrings.
        $map = [
            'shoulders' => 'shoulders',
            'shoulder' => 'shoulders',
            'أكتاف' => 'shoulders',
            'اكتاف' => 'shoulders',
            'كتاف' => 'shoulders',
            'كتف' => 'shoulders',
            'chest' => 'chest',
            'صدر' => 'chest',
            'back' => 'back',
            'ظهر' => 'back',
            'biceps' => 'biceps',
            'bicep' => 'biceps',
            'بايسبس' => 'biceps',
            'باي' => 'biceps',
            'triceps' => 'triceps',
            'tricep' => 'triceps',
            'ترايسبس' => 'triceps',
            'تراي' => 'triceps',
            'quadriceps' => 'quadriceps',
            'quad' => 'quadriceps',
            'hamstrings' => 'hamstrings',
            'hamstring' => 'hamstrings',
            'legs' => 'quadriceps',
            'leg' => 'quadriceps',
            'ارجل' => 'quadriceps',
            'أرجل' => 'quadriceps',
            'رجلين' => 'quadriceps',
            'رجل' => 'quadriceps',
            'glutes' => 'glutes',
            'glute' => 'glutes',
            'core' => 'core',
            'abs' => 'core',
            'بطن' => 'core',
        ];

        foreach ($map as $needle => $muscle) {
            if (str_contains($lower, $needle)) {
                return $muscle;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $userContext
     */
    private function detectEquipmentFilter(string $lower, array $userContext): ?string
    {
        if (preg_match('/\b(dumbbell|دمبل)\b/u', $lower)) {
            return 'dumbbell';
        }
        if (preg_match('/\b(barbell|بار)\b/u', $lower)) {
            return 'barbell';
        }
        if (preg_match('/\b(bodyweight|وزن الجسم)\b/u', $lower)) {
            return 'bodyweight';
        }

        $equipment = $userContext['training']['equipment']
            ?? $userContext['equipment']
            ?? [];
        // Profile shape is often ['mode'=>'gym','available'=>[...]] — only list items are gear names.
        if (is_array($equipment)) {
            if (array_is_list($equipment) && count($equipment) === 1) {
                $only = strtolower((string) $equipment[0]);
                if (in_array($only, ['dumbbell', 'barbell', 'bodyweight', 'cable', 'machine'], true)) {
                    return $only;
                }
            }
            $available = $equipment['available'] ?? null;
            if (is_array($available) && array_is_list($available) && count($available) === 1) {
                $only = strtolower((string) $available[0]);
                if (in_array($only, ['dumbbell', 'barbell', 'bodyweight', 'cable', 'machine'], true)) {
                    return $only;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $userContext
     */
    private function wantsHome(string $lower, array $userContext): bool
    {
        if (preg_match('/\b(home|منزلي)\b/u', $lower)) {
            return true;
        }
        $location = $userContext['training']['training_location']
            ?? $userContext['training_location']
            ?? null;

        return $location === 'home';
    }
}
