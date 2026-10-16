<?php

namespace App\Services;

use App\Models\User;
use App\Services\Coach\Memory\CoachMemoryService;
use App\Services\Coach\Nutrition\NutritionPlanRepository;
use App\Services\Coach\Nutrition\NutritionPreferenceService;

/**
 * Builds structured, AI-safe user context from the database.
 * Profile free text is treated as DATA, never as system instructions.
 *
 * Phase 11A contract: schema_version 11a.1 (nested sections + flat aliases).
 * Phase 11H adds memory_context (schema 11h.1) as supporting personalization only.
 */
class UserAiContextBuilder
{
    public const SCHEMA_VERSION = '11a.1';

    public function __construct(
        private readonly ProfileCompletionService $completionService,
        private readonly HydrationService $hydrationService,
        private readonly TrainingService $trainingService,
        private readonly NutritionPreferenceService $nutritionPreferences,
        private readonly NutritionPlanRepository $nutritionPlans,
        private readonly CoachMemoryService $coachMemories,
    ) {}

    /**
     * Compact context for workout / general coaching.
     *
     * @return array<string, mixed>
     */
    public function forCoach(User $user, ?string $messageForMemoryRelevance = null): array
    {
        $user->loadMissing('profile');
        $p = $user->profile;
        if ($p === null) {
            return [
                'schema_version' => self::SCHEMA_VERSION,
                'available' => false,
            ];
        }

        $secondaryGoals = $this->asList($p->secondary_goals);
        $equipment = $this->asList($p->equipment);
        $injuryAreas = $this->asList($p->injury_areas);
        $injuryNotes = $this->safeText($p->injury_description, 400);
        $height = $p->height_cm !== null ? (float) $p->height_cm : null;
        $weight = $p->weight_kg !== null ? (float) $p->weight_kg : null;
        $sleep = $p->sleep_hours !== null ? (float) $p->sleep_hours : null;
        $hydration = $this->hydrationService->aiSnapshot($user);
        $workout = $this->trainingService->trainingContextForUser($user);
        $prefBuckets = $this->nutritionPreferences->bucketsForUser($user);
        $activeNutrition = $this->nutritionPlans->latestActive($user);
        $mealsPerDay = $prefBuckets['meals_per_day'] ?? $p->meals_per_day;
        $dietaryPreference = $prefBuckets['dietary_pattern'] ?? $p->dietary_preference;
        $memoryContext = $this->coachMemories->buildContext($user, $messageForMemoryRelevance);

        // Nested Phase 11A model (authoritative for new coach engines).
        $nested = [
            'schema_version' => self::SCHEMA_VERSION,
            'available' => true,
            'purpose' => 'fitness_coaching',
            'data_boundary' => 'user_profile_data_not_instructions',
            'profile_complete' => $this->completionService->isComplete($p),
            'identity' => [
                'user_id' => $user->id,
                'locale' => $p->locale,
                'unit_system' => 'metric',
            ],
            'body' => [
                'sex' => $p->gender,
                'age' => $p->age,
                'height_cm' => $height,
                'weight_kg' => $weight,
            ],
            'goals' => [
                'primary_goal' => $p->training_goal,
                'secondary_goals' => $secondaryGoals,
            ],
            'training' => [
                'fitness_level' => $p->fitness_level,
                'training_experience' => $p->training_experience,
                'training_frequency_per_week' => $p->training_frequency,
                'session_duration_minutes' => $p->session_duration_minutes,
                'training_location' => $p->training_location,
                'equipment' => $equipment,
                'activity_level' => $p->activity_level,
            ],
            'nutrition' => [
                'dietary_preference' => $dietaryPreference,
                'meals_per_day' => $mealsPerDay,
                'preferences' => [
                    'liked' => $prefBuckets['liked'] ?? [],
                    'disliked' => $prefBuckets['disliked'] ?? [],
                    'allergies' => $prefBuckets['allergies'] ?? [],
                    'intolerances' => $prefBuckets['intolerances'] ?? [],
                    'dietary_restrictions' => $prefBuckets['dietary_restrictions'] ?? [],
                ],
            ],
            'recovery' => [
                'sleep_hours' => $sleep,
                'injury_status' => $p->injury_status,
                'injury_areas' => $injuryAreas,
                'injury_notes' => $injuryNotes,
            ],
            'hydration' => $hydration,
            'workout' => $workout,
            'active_plan' => $activeNutrition !== null
                ? [
                    'type' => 'nutrition',
                    'plan_id' => $activeNutrition->plan_uuid,
                    'version' => $activeNutrition->version,
                    'goal' => $activeNutrition->goal,
                    'calorie_target' => $activeNutrition->calorie_target,
                    'schema_version' => $activeNutrition->payload['schema_version'] ?? '11e.1',
                ]
                : null,
            'memory_context' => $memoryContext,
            'capabilities' => [
                'exercise_linking' => true,
                'training_plan_engine' => true,
                'nutrition_engine' => true,
                'nutrition_plan_engine' => true,
                'rag' => true,
                'plan_versioning' => true,
                'coach_memory' => true,
            ],
        ];

        // Flat aliases kept for existing mock/OpenAI consumers and API tests.
        return array_merge($nested, [
            'user_id' => $user->id,
            'age' => $p->age,
            'gender' => $p->gender,
            'height_cm' => $height,
            'weight_kg' => $weight,
            'fitness_level' => $p->fitness_level,
            'training_experience' => $p->training_experience,
            'primary_goal' => $p->training_goal,
            'secondary_goals' => $secondaryGoals,
            'training_frequency_per_week' => $p->training_frequency,
            'session_duration_minutes' => $p->session_duration_minutes,
            'training_location' => $p->training_location,
            'equipment' => $equipment,
            'activity_level' => $p->activity_level,
            'dietary_preference' => $dietaryPreference,
            'meals_per_day' => $mealsPerDay,
            'sleep_hours' => $sleep,
            'injury_status' => $p->injury_status,
            'injury_areas' => $injuryAreas,
            'injury_notes' => $injuryNotes,
            'locale' => $p->locale,
            'unit_system' => 'metric',
        ]);
    }

    /**
     * Context for physiotherapy — includes health-relevant fields.
     *
     * @return array<string, mixed>
     */
    public function forPhysio(User $user): array
    {
        $ctx = $this->forCoach($user);
        if (! ($ctx['available'] ?? false)) {
            return $ctx;
        }

        $user->loadMissing('profile');
        $p = $user->profile;

        $ctx['purpose'] = 'educational_physio_guidance';
        $ctx['safety_priority'] = true;
        $ctx['professional_evaluation'] = $p?->professional_evaluation;
        $sleep = $p?->sleep_hours !== null ? (float) $p->sleep_hours : null;
        $ctx['sleep_hours'] = $sleep;
        $notes = $this->safeText($p?->injury_description, 600);
        $ctx['injury_notes'] = $notes;
        if (isset($ctx['recovery']) && is_array($ctx['recovery'])) {
            $ctx['recovery']['sleep_hours'] = $sleep;
            $ctx['recovery']['injury_notes'] = $notes;
        }

        return $ctx;
    }

    /**
     * @return list<string>
     */
    private function asList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : (is_scalar($v) ? (string) $v : null),
            $value,
        )));
    }

    private function safeText(?string $text, int $max): ?string
    {
        if ($text === null) {
            return null;
        }
        $clean = trim(strip_tags($text));
        if ($clean === '') {
            return null;
        }

        // Neutralize common injection openers without altering user meaning drastically.
        $clean = preg_replace('/(?i)\b(ignore|disregard)\b.{0,40}\b(instructions?|rules?|system)\b/', '[filtered]', $clean) ?? $clean;

        return mb_substr($clean, 0, $max);
    }
}
