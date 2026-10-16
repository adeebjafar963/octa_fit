<?php

namespace App\Services\Coach\Validation;

use App\Services\Coach\Semantic\IntentOntology;
use App\Services\Coach\Semantic\SemanticIntentContract;

/**
 * Pre-response validation: did we answer the CURRENT intent without topic bleed?
 */
class CoachAnswerValidator
{
    /**
     * @param  array<string, mixed>  $routing
     * @param  array<string, mixed>  $topicDecision
     * @param  array<string, mixed>|null  $nutrition
     * @param  array<string, mixed>|null  $nutritionPlan
     * @param  array<string, mixed>|null  $trainingPlan
     * @param  list<array<string, mixed>>  $groundedExercises
     * @return array{ok: bool, errors: list<string>, retry_capability: string|null}
     */
    public function validate(
        SemanticIntentContract $intent,
        array $routing,
        array $topicDecision,
        ?array $nutrition,
        ?array $nutritionPlan,
        ?array $trainingPlan,
        array $groundedExercises = [],
        ?string $responseText = null,
    ): array {
        $errors = [];
        $topic = IntentOntology::topicBucket($intent->intent, $intent->domain);
        $isSwitch = ($topicDecision['is_topic_switch'] ?? false) === true;

        // Topic switch must not keep answering previous domain engines.
        if ($isSwitch) {
            $prev = $topicDecision['previous_topic'] ?? null;
            if ($prev === 'nutrition' && $topic === 'training' && $nutritionPlan !== null && $trainingPlan === null
                && $intent->intent !== 'nutrition_plan_create') {
                $errors[] = 'topic_switch_nutrition_bleed';
            }
            if ($prev === 'training' && $topic === 'nutrition' && $trainingPlan !== null && $nutrition === null
                && $intent->intent === 'nutrition_lookup') {
                $errors[] = 'topic_switch_training_bleed';
            }
        }

        if ($intent->intent === 'nutrition_lookup') {
            if (! (is_array($nutrition) && ($nutrition['ok'] ?? false))) {
                // Soft: lookup may fall through; only hard-fail if a training plan was produced instead.
                if ($trainingPlan !== null) {
                    $errors[] = 'nutrition_lookup_answered_with_training_plan';
                }
            }
            if ($nutritionPlan !== null) {
                $errors[] = 'nutrition_lookup_answered_with_nutrition_plan';
            }
        }

        if ($intent->intent === 'training_plan_create' && $trainingPlan === null && ($routing['is_plan_request'] ?? false)) {
            if ($nutritionPlan !== null) {
                $errors[] = 'training_plan_request_answered_with_nutrition_plan';
            }
        }

        if ($intent->intent === 'training_exercise_recommendation') {
            if ($nutritionPlan !== null || (is_array($nutrition) && ($nutrition['ok'] ?? false))) {
                $errors[] = 'exercise_request_answered_with_nutrition';
            }
            // Scope control: body-part asks must not be answered with a multi-day full plan.
            $scope = $intent->entities['scope'] ?? $intent->constraints['scope'] ?? null;
            $bodyPart = $intent->entities['body_part'] ?? $intent->constraints['body_part'] ?? null;
            if ($bodyPart !== null && $trainingPlan !== null) {
                $sessionCount = count($trainingPlan['sessions'] ?? []);
                if ($sessionCount > 1 || $scope === 'body_part') {
                    $errors[] = 'body_part_request_answered_with_full_plan';
                }
            }
        }

        if ($intent->intent === 'general_sports_knowledge') {
            if ($trainingPlan !== null || $nutritionPlan !== null) {
                $errors[] = 'knowledge_answered_with_plan';
            }
        }

        if ($intent->intent === 'ambiguous_request' && $responseText !== null) {
            // Clarification responses are short; giant dumps fail.
            if (mb_strlen($responseText) > 400) {
                $errors[] = 'ambiguous_overlong_response';
            }
        }

        $retry = null;
        if (in_array('topic_switch_nutrition_bleed', $errors, true)
            || in_array('training_plan_request_answered_with_nutrition_plan', $errors, true)) {
            $retry = 'training';
        }
        if (in_array('topic_switch_training_bleed', $errors, true)
            || in_array('exercise_request_answered_with_nutrition', $errors, true)
            || in_array('nutrition_lookup_answered_with_training_plan', $errors, true)) {
            $retry = 'nutrition_or_current';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'retry_capability' => $retry,
        ];
    }
}
