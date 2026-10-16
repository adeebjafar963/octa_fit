<?php

namespace App\Services\Coach;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phase 11C — deterministic training plan generation using Phase 11B grounded exercises.
 */
class TrainingPlanEngine
{
    public function __construct(
        private readonly TrainingIntentExtractor $intentExtractor,
        private readonly TrainingKnowledge $knowledge,
        private readonly CoachExerciseRetrievalService $exerciseRetrieval,
        private readonly TrainingPlanValidator $validator,
        private readonly CoachRequestResultValidator $requestValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $userContext
     * @return array{plan: array<string, mixed>|null, intent: array<string, mixed>, errors: list<string>}
     */
    public function generateFromMessage(string $message, array $userContext = [], ?string $language = 'en'): array
    {
        $intent = $this->intentExtractor->extract($message, $userContext);
        if (! ($intent['is_plan_request'] ?? false)) {
            return ['plan' => null, 'intent' => $intent, 'errors' => []];
        }

        return $this->generate($intent, $userContext, $language);
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $userContext
     * @return array{plan: array<string, mixed>|null, intent: array<string, mixed>, errors: list<string>}
     */
    public function generate(array $intent, array $userContext = [], ?string $language = 'en'): array
    {
        $lang = $language === 'ar' ? 'ar' : 'en';
        $goal = (string) ($intent['goal'] ?? 'hypertrophy');
        $discipline = (string) ($intent['discipline'] ?? 'bodybuilding');
        $level = (string) ($intent['level'] ?? 'beginner');
        $days = (int) ($intent['days_per_week'] ?? 4);
        $days = min(6, max(2, $days));
        $split = (string) ($intent['preferred_split'] ?? 'upper_lower');
        $equipmentMode = (string) ($intent['equipment_mode'] ?? 'gym');
        $targetDuration = (int) ($intent['session_duration_minutes'] ?? 60);
        $targetDuration = min(180, max(20, $targetDuration));

        $templates = $this->knowledge->sessionTemplates($split, $days, $discipline);
        $maxPerSession = $this->knowledge->exercisesPerSession($level, $targetDuration);
        // For long MMA sessions, expand conditioning volume (rounds).
        $mmaRoundScale = $discipline === 'mma' && $targetDuration >= 90
            ? max(3, (int) round($targetDuration / 30))
            : 3;
        $usedIds = [];
        $sessions = [];
        $pool = [];

        foreach ($templates as $index => $template) {
            $sessionExercises = [];
            $order = 1;

            foreach ($template['slots'] as $slot) {
                if (count($sessionExercises) >= $maxPerSession) {
                    break;
                }

                $picked = $this->pickExercise(
                    $slot,
                    $discipline,
                    $level,
                    $equipmentMode,
                    $usedIds,
                    $lang,
                    $pool,
                );

                if ($picked === null) {
                    // Try without excluding used IDs (allow cross-session reuse, not within session).
                    $sessionIds = array_column($sessionExercises, 'exercise_id');
                    $picked = $this->pickExercise(
                        $slot,
                        $discipline,
                        $level,
                        $equipmentMode,
                        $sessionIds,
                        $lang,
                        $pool,
                    );
                }

                if ($picked === null) {
                    Log::debug('coach.training_plan.slot_unfilled', [
                        'day' => $index + 1,
                        'slot' => $slot,
                    ]);

                    continue;
                }

                $role = $slot['role'] ?? 'compound';
                $rx = $this->knowledge->prescriptionFor($goal, $level, $role);
                $exerciseType = $picked['exercise_type'] ?? 'sets_reps';

                $planExercise = [
                    'exercise_id' => $picked['exercise_id'],
                    'name' => $picked['name'],
                    'name_en' => $picked['name_en'] ?? $picked['name'],
                    'name_ar' => $picked['name_ar'] ?? null,
                    'slug' => $picked['slug'] ?? null,
                    'order' => $order++,
                    'sets' => $rx['sets'],
                    'reps' => $rx['reps_min'].'-'.$rx['reps_max'],
                    'reps_min' => $rx['reps_min'],
                    'reps_max' => $rx['reps_max'],
                    'duration_seconds' => null,
                    'rest_seconds' => $rx['rest_seconds'],
                    'rounds' => null,
                    'notes' => $rx['notes'],
                    'progression_note' => 'Add reps in range, then load, when form is solid.',
                    'grounded' => true,
                    'flutter_route' => $picked['flutter_route'] ?? ('/training/exercises/'.$picked['exercise_id']),
                    'equipment' => $picked['equipment'] ?? null,
                    'exercise_type' => $exerciseType,
                ];

                // Timed / round-based MMA-style work when exercise type suggests it.
                if (in_array($exerciseType, ['timed', 'duration', 'rounds'], true) || $goal === 'conditioning' && ($discipline === 'mma')) {
                    if (($picked['movement_pattern'] ?? '') !== 'horizontal_push') {
                        // Keep sets/reps for strength pieces; conditioning slots can use rounds.
                        if (($slot['role'] ?? '') === 'accessory' || ($template['focus'] ?? '') === 'conditioning') {
                            $planExercise['rounds'] = $mmaRoundScale;
                            $planExercise['duration_seconds'] = $targetDuration >= 120 ? 60 : 40;
                            $planExercise['reps'] = null;
                            $planExercise['reps_min'] = null;
                            $planExercise['reps_max'] = null;
                            $planExercise['rest_seconds'] = 30;
                        }
                    }
                }

                $sessionExercises[] = $planExercise;
                $usedIds[] = $picked['exercise_id'];
            }

            if ($sessionExercises === []) {
                continue;
            }

            $avgSets = (int) round(array_sum(array_column($sessionExercises, 'sets')) / max(1, count($sessionExercises)));
            $avgRest = (int) round(array_sum(array_map(fn ($e) => (int) ($e['rest_seconds'] ?? 60), $sessionExercises)) / max(1, count($sessionExercises)));
            $estimated = $this->knowledge->estimateSessionMinutes(
                count($sessionExercises),
                $avgSets,
                $avgRest,
                $targetDuration,
            );

            $sessions[] = [
                'day' => count($sessions) + 1,
                'title' => $template['title'],
                'focus' => $template['focus'],
                'estimated_duration_minutes' => $estimated,
                'exercises' => $sessionExercises,
            ];
        }

        $plan = [
            'schema_version' => '11c.1',
            'id' => (string) Str::uuid(),
            'version' => 1,
            'title' => $this->titleFor($goal, $days, $lang),
            'goal' => $goal,
            'discipline' => $discipline,
            'level' => $level,
            'equipment_mode' => $equipmentMode,
            'days_per_week' => count($sessions),
            'requested_days_per_week' => $days,
            'preferred_split' => $split,
            'requested_session_duration_minutes' => $targetDuration,
            'estimated_session_duration_minutes' => $sessions[0]['estimated_duration_minutes'] ?? $targetDuration,
            'description' => $this->descriptionFor($goal, $discipline, $level, $lang),
            'progression_strategy' => $this->knowledge->progressionStrategy($goal, $level),
            'recovery_notes' => $this->recoveryNotes($userContext, $intent),
            'sessions' => $sessions,
            'grounded' => true,
            'nutrition_included' => false,
            'limitations' => $this->limitations($sessions, $days, $intent),
            'created_at' => now()->toIso8601String(),
            'evidence' => [
                'tag' => 'resistance_training_general',
                'note' => 'Programming uses evidence-aware coaching conventions; detailed citations deferred to Phase 11F.',
            ],
        ];

        $validation = $this->validator->validate($plan);
        if (! $validation['ok']) {
            return [
                'plan' => null,
                'intent' => $intent,
                'errors' => $validation['errors'],
            ];
        }

        $requestErrors = $this->requestValidator->validateTrainingPlan($intent, $validation['plan']);
        if ($requestErrors !== []) {
            Log::warning('coach.training_plan.request_mismatch', [
                'errors' => $requestErrors,
                'discipline' => $discipline,
                'days' => $days,
                'duration' => $targetDuration,
            ]);
            // Soft: keep plan but surface mismatch in limitations for observability.
            $validation['plan']['limitations'] = array_values(array_unique(array_merge(
                $validation['plan']['limitations'] ?? [],
                $requestErrors,
            )));
        }

        return [
            'plan' => $validation['plan'],
            'intent' => $intent,
            'errors' => [],
        ];
    }

    /**
     * Intensify a single day of an existing grounded plan (follow-up).
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function intensifyDay(array $plan, int $day): array
    {
        $sessions = $plan['sessions'] ?? [];
        if (! is_array($sessions)) {
            return $plan;
        }

        foreach ($sessions as $i => $session) {
            if ((int) ($session['day'] ?? ($i + 1)) !== $day) {
                continue;
            }
            $exercises = $session['exercises'] ?? [];
            if (! is_array($exercises)) {
                break;
            }
            foreach ($exercises as $j => $ex) {
                if (! is_array($ex)) {
                    continue;
                }
                $sets = (int) ($ex['sets'] ?? 3);
                $ex['sets'] = min(6, $sets + 1);
                if (isset($ex['reps_max']) && is_numeric($ex['reps_max'])) {
                    $ex['reps_max'] = (int) $ex['reps_max'] + 2;
                    $ex['reps_min'] = (int) ($ex['reps_min'] ?? 8);
                    $ex['reps'] = $ex['reps_min'].'-'.$ex['reps_max'];
                }
                if (isset($ex['rounds']) && is_numeric($ex['rounds'])) {
                    $ex['rounds'] = min(10, (int) $ex['rounds'] + 1);
                }
                $ex['notes'] = trim(($ex['notes'] ?? '').' Intensity increased for day '.$day.'.');
                $exercises[$j] = $ex;
            }
            $session['exercises'] = $exercises;
            $session['title'] = ($session['title'] ?? 'Session').' (harder)';
            $sessions[$i] = $session;
            break;
        }

        $plan['sessions'] = $sessions;
        $plan['version'] = (int) ($plan['version'] ?? 1) + 1;

        return $plan;
    }

    /**
     * Replace the last exercise on a given day with another grounded exercise.
     *
     * @param  array<string, mixed>  $plan
     * @return array{plan: array<string, mixed>|null, errors: list<string>}
     */
    public function replaceLastExerciseOnDay(array $plan, ?int $day, string $language = 'en'): array
    {
        $sessions = $plan['sessions'] ?? [];
        if (! is_array($sessions) || $sessions === []) {
            return ['plan' => null, 'errors' => ['no_sessions']];
        }

        if ($day === null) {
            if (count($sessions) !== 1) {
                return ['plan' => null, 'errors' => ['day_ambiguous']];
            }
            $day = (int) ($sessions[0]['day'] ?? 1);
        }

        $discipline = (string) ($plan['discipline'] ?? 'bodybuilding');
        $level = (string) ($plan['level'] ?? 'beginner');
        $equipmentMode = $discipline === 'home_calisthenics' ? 'home' : 'gym';
        $lang = $language === 'ar' ? 'ar' : 'en';

        $targetIndex = null;
        foreach ($sessions as $i => $session) {
            if ((int) ($session['day'] ?? ($i + 1)) === $day) {
                $targetIndex = $i;
                break;
            }
        }
        if ($targetIndex === null) {
            return ['plan' => null, 'errors' => ['day_not_found']];
        }

        $exercises = $sessions[$targetIndex]['exercises'] ?? [];
        if (! is_array($exercises) || $exercises === []) {
            return ['plan' => null, 'errors' => ['no_exercises_on_day']];
        }

        $lastIdx = count($exercises) - 1;
        $old = $exercises[$lastIdx];
        $oldId = (int) ($old['exercise_id'] ?? 0);
        $exclude = [];
        foreach ($exercises as $ex) {
            if (isset($ex['exercise_id'])) {
                $exclude[] = (int) $ex['exercise_id'];
            }
        }

        $pool = [];
        $slot = ['search' => '', 'role' => 'accessory'];
        // Prefer same movement family when searchable name token exists.
        $nameHint = mb_strtolower((string) ($old['name_en'] ?? $old['name'] ?? ''));
        foreach (['jab', 'cross', 'hook', 'kick', 'sprawl', 'push', 'squat', 'press', 'row', 'curl'] as $token) {
            if (str_contains($nameHint, $token)) {
                $slot['search'] = $token;
                break;
            }
        }

        $picked = $this->pickExercise($slot, $discipline, $level, $equipmentMode, $exclude, $lang, $pool);
        if ($picked === null && $slot['search'] !== '') {
            $slot['search'] = '';
            $picked = $this->pickExercise($slot, $discipline, $level, $equipmentMode, $exclude, $lang, $pool);
        }
        if ($picked === null) {
            return ['plan' => null, 'errors' => ['no_replacement_found']];
        }
        if ((int) $picked['exercise_id'] === $oldId) {
            return ['plan' => null, 'errors' => ['replacement_same_as_old']];
        }

        $replacement = $old;
        $replacement['exercise_id'] = $picked['exercise_id'];
        $replacement['name'] = $picked['name'];
        $replacement['name_en'] = $picked['name_en'] ?? $picked['name'];
        $replacement['name_ar'] = $picked['name_ar'] ?? null;
        $replacement['slug'] = $picked['slug'] ?? null;
        $replacement['flutter_route'] = $picked['flutter_route']
            ?? ('/training/exercises/'.$picked['exercise_id']);
        $replacement['equipment'] = $picked['equipment'] ?? null;
        $replacement['grounded'] = true;
        $replacement['notes'] = trim(($old['notes'] ?? '').' Replaced previous exercise.');

        $exercises[$lastIdx] = $replacement;
        $sessions[$targetIndex]['exercises'] = $exercises;
        $plan['sessions'] = $sessions;
        $plan['version'] = (int) ($plan['version'] ?? 1) + 1;

        $validation = $this->validator->validate($plan);
        if (! $validation['ok']) {
            return ['plan' => null, 'errors' => $validation['errors']];
        }

        return ['plan' => $validation['plan'], 'errors' => []];
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  list<int>  $excludeIds
     * @param  array<string, list<array<string, mixed>>>  $pool
     * @return array<string, mixed>|null
     */
    private function pickExercise(
        array $slot,
        string $discipline,
        string $level,
        string $equipmentMode,
        array $excludeIds,
        string $language,
        array &$pool,
    ): ?array {
        $filters = [
            'type' => $discipline,
            'per_page' => 40,
            'exclude_ids' => $excludeIds,
        ];

        // Prefer matching level but fall back without level if empty.
        $filtersWithLevel = $filters + ['level' => $level];

        if (! empty($slot['search'])) {
            $filtersWithLevel['search'] = $slot['search'];
            $filters['search'] = $slot['search'];
        }
        if (! empty($slot['muscle'])) {
            $filtersWithLevel['muscle'] = $slot['muscle'];
            $filters['muscle'] = $slot['muscle'];
        }
        if (! empty($slot['pattern'])) {
            $filtersWithLevel['movement_pattern'] = $slot['pattern'];
            $filters['movement_pattern'] = $slot['pattern'];
        }
        if ($equipmentMode === 'home' || $discipline === 'home_calisthenics') {
            $filtersWithLevel['home_friendly'] = true;
            $filters['home_friendly'] = true;
        }
        // Never apply dumbbell-only filter for MMA — it empties the combat pool.
        if ($equipmentMode === 'dumbbell' && $discipline !== 'mma') {
            $filtersWithLevel['equipment'] = 'dumbbells';
            $filters['equipment'] = 'dumbbells';
        }

        $key = md5(json_encode($filtersWithLevel) ?: '');
        if (! isset($pool[$key])) {
            $pool[$key] = $this->exerciseRetrieval->retrieveByFilters($filtersWithLevel, $language);
            if ($pool[$key] === []) {
                $pool[$key] = $this->exerciseRetrieval->retrieveByFilters($filters, $language);
            }
            // Last resort: discipline-only pool.
            if ($pool[$key] === []) {
                $fallback = ['type' => $discipline, 'per_page' => 40, 'exclude_ids' => $excludeIds];
                if ($equipmentMode === 'home') {
                    $fallback['home_friendly'] = true;
                }
                $pool[$key] = $this->exerciseRetrieval->retrieveByFilters($fallback, $language);
            }
        }

        foreach ($pool[$key] as $candidate) {
            $id = (int) ($candidate['exercise_id'] ?? 0);
            if ($id <= 0 || in_array($id, $excludeIds, true)) {
                continue;
            }
            if (! ($candidate['grounded'] ?? false)) {
                continue;
            }
            if ($equipmentMode === 'home' && isset($candidate['equipment'])) {
                $eq = strtolower((string) $candidate['equipment']);
                $blocked = ['barbell', 'cable', 'machine', 'smith'];
                foreach ($blocked as $b) {
                    if (str_contains($eq, $b) && ! str_contains($eq, 'bodyweight')) {
                        // still allow if home_friendly was filtered — skip strict block
                    }
                }
            }

            return $candidate;
        }

        return null;
    }

    private function titleFor(string $goal, int $days, string $lang): string
    {
        $goalLabel = match ($goal) {
            'hypertrophy' => $lang === 'ar' ? 'تضخيم / تضخم عضلي' : 'Hypertrophy / Muscle Gain',
            'strength' => $lang === 'ar' ? 'قوة' : 'Strength',
            'fat_loss' => $lang === 'ar' ? 'حرق دهون مع الحفاظ على العضلات' : 'Fat Loss (Muscle Preservation)',
            'conditioning' => $lang === 'ar' ? 'إعداد بدني MMA' : 'MMA Conditioning Support',
            'general_fitness' => $lang === 'ar' ? 'لياقة عامة' : 'General Fitness',
            default => $lang === 'ar' ? 'تدريب' : 'Training',
        };

        return $lang === 'ar'
            ? "خطة {$days} أيام — {$goalLabel}"
            : "{$days}-Day {$goalLabel} Plan";
    }

    private function descriptionFor(string $goal, string $discipline, string $level, string $lang): string
    {
        if ($lang === 'ar') {
            return "خطة تدريب مخصصة ({$level}) لهدف {$goal} ضمن تخصص {$discipline}. التمارين من مكتبة أوكتا فيت فقط. لا تتضمن تغذية.";
        }

        return "Personalized {$level} {$goal} training plan for {$discipline}. "
            .'All exercises are grounded OCTA FIT library records. Nutrition is not included in this phase.';
    }

    /**
     * @param  array<string, mixed>  $userContext
     * @param  array<string, mixed>  $intent
     */
    private function recoveryNotes(array $userContext, array $intent): string
    {
        $parts = [
            'Space hard sessions for overlapping muscle groups when possible.',
            'Sleep and hydration support recovery; this plan does not diagnose fatigue.',
        ];

        $injury = $intent['constraints']['injury_status'] ?? 'none';
        if ($injury !== 'none' && $injury !== null) {
            $parts[] = 'Injury status is marked in your profile — avoid painful ranges and seek professional care if symptoms persist.';
        }

        $recent = $userContext['workout']['recent_training_types'] ?? [];
        if (is_array($recent) && $recent !== []) {
            $parts[] = 'Recent training types noted: '.implode(', ', array_map('strval', array_slice($recent, 0, 4))).'.';
        }

        return implode(' ', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @param  array<string, mixed>  $intent
     * @return list<string>
     */
    private function limitations(array $sessions, int $requestedDays, array $intent): array
    {
        $notes = [];
        if (count($sessions) < $requestedDays) {
            $notes[] = 'Requested '.$requestedDays.' days but only '.count($sessions).' sessions could be filled with grounded exercises.';
        }
        if (($intent['source']['from_profile_frequency'] ?? false) === true) {
            $notes[] = 'Days/week used a profile default or safe default because the message did not specify an explicit frequency.';
        }
        $notes[] = 'Nutrition/calories are out of scope for Phase 11C.';

        return $notes;
    }
}
