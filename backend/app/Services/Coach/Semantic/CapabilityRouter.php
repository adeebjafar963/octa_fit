<?php

namespace App\Services\Coach\Semantic;

/**
 * Maps semantic intent → legacy routing flags for CoachService engines.
 */
class CapabilityRouter
{
    /**
     * @return array<string, mixed>
     */
    public function toRouting(SemanticIntentContract $intent, array $trainingIntent): array
    {
        $capabilities = $intent->capabilities;
        $isFood = $intent->intent === 'nutrition_lookup' || in_array('nutrition_lookup', $capabilities, true);
        $isNutritionPlan = in_array($intent->intent, ['nutrition_plan_create', 'nutrition_plan_modify'], true)
            || in_array('nutrition_plan', $capabilities, true);
        $isExerciseRec = $intent->intent === 'training_exercise_recommendation'
            || in_array('grounded_exercises', $capabilities, true);
        $isGeneralSports = $intent->intent === 'general_sports_knowledge';
        $isFollowUp = in_array($intent->intent, ['training_plan_modify', 'training_plan_resume'], true)
            && $intent->needsActivePlan();
        $isAmbiguous = $intent->intent === 'ambiguous_request';
        $isSafety = $intent->domain === 'safety' || $intent->intent === 'safety';
        $isPlan = (
            $intent->intent === 'training_plan_create'
            || in_array('training_plan', $capabilities, true)
            || ($trainingIntent['is_plan_request'] ?? false)
        ) && ! $isFood && ! $isNutritionPlan && ! $isAmbiguous && ! $isExerciseRec && $intent->domain !== 'nutrition';
        $needsPersonalization = (bool) ($intent->contextRequirements['profile'] ?? false)
            && ! $isFood
            && ! $isGeneralSports
            && ! $isFollowUp
            && ! $isExerciseRec
            && ! $isAmbiguous;

        $legacyIntent = match ($intent->intent) {
            'nutrition_lookup' => 'food_lookup',
            'nutrition_plan_create', 'nutrition_plan_modify' => 'nutrition_plan',
            'training_plan_create' => 'training_plan',
            'training_plan_modify', 'training_plan_resume' => 'active_plan_follow_up',
            'training_exercise_recommendation', 'exercise_lookup' => 'exercise_recommendation',
            'general_sports_knowledge' => 'general_sports_question',
            'ambiguous_request' => 'clarification',
            'unsupported_request', 'prompt_injection' => 'unsupported',
            'safety' => 'medical',
            'memory_related' => 'memory',
            default => 'general',
        };

        return [
            'intent' => $legacyIntent,
            'domain' => match ($intent->domain) {
                'nutrition' => 'nutrition',
                'training', 'sports_knowledge' => 'training',
                'safety' => 'safety',
                'system' => 'system',
                default => 'general',
            },
            'language' => $intent->language,
            'discipline' => $trainingIntent['discipline'] ?? null,
            'goal' => $trainingIntent['goal'] ?? null,
            'equipment_mode' => $trainingIntent['equipment_mode'] ?? ($intent->entities['equipment_mode'] ?? null),
            'days' => $trainingIntent['days_per_week'] ?? null,
            'duration_minutes' => $trainingIntent['session_duration_minutes'] ?? null,
            'level' => $trainingIntent['level'] ?? null,
            'body_part' => $intent->entities['body_part'] ?? $intent->constraints['body_part'] ?? null,
            'discipline_explicit' => (bool) ($trainingIntent['discipline_explicit'] ?? false),
            'days_explicit' => (bool) ($trainingIntent['days_explicit'] ?? false),
            'duration_explicit' => (bool) ($trainingIntent['duration_explicit'] ?? false),
            'goal_explicit' => (bool) ($trainingIntent['goal_explicit'] ?? false),
            'needs_personalization' => $needsPersonalization,
            'needs_grounded_exercises' => $isPlan || $isFollowUp || $isExerciseRec,
            'needs_food_database' => $isFood,
            'needs_nutrition_plan' => $isNutritionPlan,
            'needs_general_knowledge' => $isFood || $isGeneralSports || $intent->intent === 'general_conversation',
            'needs_rag' => $isGeneralSports,
            'follow_up' => $isFollowUp,
            'response_mode' => $intent->responseMode,
            'skip_profile' => $isFood || $isExerciseRec || $isAmbiguous || ($isGeneralSports && ! $needsPersonalization),
            'is_plan_request' => $isPlan && ! $isFollowUp && ! $isExerciseRec && ! $isAmbiguous,
            'is_food_lookup' => $isFood,
            'is_nutrition_plan' => $isNutritionPlan,
            'is_exercise_recommendation' => $isExerciseRec,
            'is_general_sports' => $isGeneralSports,
            'is_ambiguous' => $isAmbiguous,
            'is_safety' => $isSafety,
            'confidence' => $intent->confidence,
            'semantic_intent' => $intent->intent,
            'semantic_modification' => $intent->modification(),
            'capabilities' => $isSafety
                ? array_values(array_unique(array_merge($capabilities, ['safety'])))
                : $capabilities,
            'clarification_required' => $isAmbiguous || $intent->responseMode === 'clarification',
        ];
    }

    /**
     * @param  array<string, mixed>  $activeIntent
     * @return array{
     *   is_follow_up: bool,
     *   action: string|null,
     *   duration_minutes: int|null,
     *   day: int|null,
     *   remove_equipment: string|null,
     *   target_exercise: string|null,
     *   merged_intent: array<string, mixed>
     * }
     */
    public function toFollowUp(SemanticIntentContract $intent, array $activeIntent): array
    {
        $result = [
            'is_follow_up' => false,
            'action' => null,
            'duration_minutes' => null,
            'day' => null,
            'remove_equipment' => null,
            'target_exercise' => null,
            'merged_intent' => $activeIntent,
        ];

        if ($intent->intent === 'training_plan_resume') {
            return array_merge($result, [
                'is_follow_up' => true,
                'action' => 'resume_plan',
                'merged_intent' => array_merge($activeIntent, ['is_plan_request' => true]),
            ]);
        }

        if ($intent->intent !== 'training_plan_modify') {
            return $result;
        }

        $mod = $intent->modification();
        $day = $intent->targetDay();

        $result['is_follow_up'] = true;
        $result['day'] = $day;
        $result['merged_intent'] = $activeIntent;

        return match ($mod) {
            'increase_difficulty' => array_merge($result, ['action' => 'intensify_day']),
            'replace_last_exercise' => array_merge($result, [
                'action' => 'replace_last_exercise',
                'target_exercise' => 'last',
            ]),
            'update_duration' => array_merge($result, [
                'action' => 'update_duration',
                'duration_minutes' => (int) ($intent->constraints['duration_minutes'] ?? 0) ?: null,
                'merged_intent' => array_merge($activeIntent, [
                    'session_duration_minutes' => (int) ($intent->constraints['duration_minutes'] ?? 0),
                    'duration_explicit' => true,
                    'is_plan_request' => true,
                ]),
            ]),
            default => $result,
        };
    }
}
