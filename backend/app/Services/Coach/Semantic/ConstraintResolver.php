<?php

namespace App\Services\Coach\Semantic;

use App\Services\Coach\TrainingIntentExtractor;

/**
 * Merges constraints with authority: CURRENT_REQUEST > ACTIVE_CONTEXT > PROFILE > MEMORY > DEFAULT.
 */
class ConstraintResolver
{
    public function __construct(
        private readonly TrainingIntentExtractor $trainingIntent,
    ) {}

    /**
     * @param  array<string, mixed>  $userContext
     * @param  array<string, mixed>  $semanticConstraints
     * @param  array<string, mixed>|null  $activeIntent
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $userContext
     * @param  array<string, mixed>  $semanticConstraints
     * @param  array<string, mixed>|null  $activeIntent
     * @return array<string, mixed>
     */
    public function resolveTrainingIntent(
        string $message,
        array $userContext,
        array $semanticConstraints,
        ?array $activeIntent = null,
        bool $forcePlanRequest = false,
    ): array {
        $fromMessage = $this->trainingIntent->extract($message, $userContext);

        // Semantic constraints from NLU override profile defaults when present.
        $merged = $fromMessage;
        if (isset($semanticConstraints['days'])) {
            $merged['days_per_week'] = (int) $semanticConstraints['days'];
            $merged['days_explicit'] = true;
        }
        if (isset($semanticConstraints['days_per_week'])) {
            $merged['days_per_week'] = (int) $semanticConstraints['days_per_week'];
            $merged['days_explicit'] = true;
        }
        if (isset($semanticConstraints['duration_minutes'])) {
            $merged['session_duration_minutes'] = (int) $semanticConstraints['duration_minutes'];
            $merged['duration_explicit'] = true;
        }
        if (isset($semanticConstraints['discipline'])) {
            $merged['discipline'] = (string) $semanticConstraints['discipline'];
            $merged['discipline_explicit'] = true;
        }
        if (isset($semanticConstraints['equipment_mode'])) {
            $merged['equipment_mode'] = (string) $semanticConstraints['equipment_mode'];
            $merged['equipment_explicit'] = true;
        }
        // Message mentions gym machines/weights — never keep profile home.
        $msg = mb_strtolower($message);
        if (preg_match('/(اجهزه|أجهزة|اوزان|أوزان|بالجيم|للجيم|gym|weights?|machines?)/u', $msg)
            && ! preg_match('/(بالبيت|home|بدون\s*معدات)/u', $msg)) {
            $merged['equipment_mode'] = 'gym';
            $merged['equipment_explicit'] = true;
        }
        if (isset($semanticConstraints['goal'])) {
            $merged['goal'] = (string) $semanticConstraints['goal'];
            $merged['goal_explicit'] = true;
        }

        // Active plan intent fills gaps only.
        if ($activeIntent !== null) {
            foreach (['discipline', 'goal', 'level', 'days_per_week', 'session_duration_minutes', 'equipment_mode'] as $key) {
                if (($merged[$key] ?? null) === null && isset($activeIntent[$key])) {
                    $merged[$key] = $activeIntent[$key];
                }
            }
        }

        // Semantic TRAINING_PLAN_CREATE always forces engine execution — never re-gate on keywords.
        if ($forcePlanRequest) {
            $merged['is_plan_request'] = true;
            $merged['intent'] = 'training_plan';
            $merged['domain'] = 'training';
        }

        return $merged;
    }
}
