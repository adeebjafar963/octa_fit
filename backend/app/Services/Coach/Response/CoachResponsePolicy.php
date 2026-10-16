<?php

namespace App\Services\Coach\Response;

use App\Services\Coach\Semantic\SemanticIntentContract;

/**
 * Explicit response shaping policy by intent class.
 */
class CoachResponsePolicy
{
    /**
     * @param  array<string, mixed>  $topicDecision
     * @return array{
     *   mode: string,
     *   allow_profile_lecture: bool,
     *   allow_active_plan: bool,
     *   allow_rag_dump: bool,
     *   allow_unrelated_topic: bool,
     *   prefer_clarification: bool
     * }
     */
    public function forIntent(SemanticIntentContract $intent, array $topicDecision = []): array
    {
        $isSwitch = ($topicDecision['is_topic_switch'] ?? false) === true;

        return match ($intent->intent) {
            'nutrition_lookup' => [
                'mode' => 'direct_factual',
                'allow_profile_lecture' => false,
                'allow_active_plan' => false,
                'allow_rag_dump' => false,
                'allow_unrelated_topic' => false,
                'prefer_clarification' => false,
            ],
            'general_sports_knowledge' => [
                'mode' => 'concise_education',
                'allow_profile_lecture' => false,
                'allow_active_plan' => false,
                'allow_rag_dump' => false,
                'allow_unrelated_topic' => false,
                'prefer_clarification' => false,
            ],
            'training_plan_create', 'nutrition_plan_create' => [
                'mode' => 'structured_plan',
                'allow_profile_lecture' => ! $isSwitch,
                'allow_active_plan' => false,
                'allow_rag_dump' => false,
                'allow_unrelated_topic' => false,
                'prefer_clarification' => $intent->confidence < 0.55,
            ],
            'training_plan_modify', 'training_plan_resume' => [
                'mode' => 'follow_up_modify',
                'allow_profile_lecture' => false,
                'allow_active_plan' => true,
                'allow_rag_dump' => false,
                'allow_unrelated_topic' => false,
                'prefer_clarification' => false,
            ],
            'training_exercise_recommendation' => [
                'mode' => 'grounded_exercises',
                'allow_profile_lecture' => false,
                'allow_active_plan' => false,
                'allow_rag_dump' => false,
                'allow_unrelated_topic' => false,
                'prefer_clarification' => false,
            ],
            'ambiguous_request' => [
                'mode' => 'clarification',
                'allow_profile_lecture' => false,
                'allow_active_plan' => false,
                'allow_rag_dump' => false,
                'allow_unrelated_topic' => false,
                'prefer_clarification' => true,
            ],
            'safety' => [
                'mode' => 'safety',
                'allow_profile_lecture' => false,
                'allow_active_plan' => false,
                'allow_rag_dump' => false,
                'allow_unrelated_topic' => false,
                'prefer_clarification' => false,
            ],
            default => [
                'mode' => 'general',
                'allow_profile_lecture' => false,
                'allow_active_plan' => false,
                'allow_rag_dump' => false,
                'allow_unrelated_topic' => false,
                'prefer_clarification' => $intent->confidence < 0.5,
            ],
        };
    }

    public function clarificationMessage(string $lang, string $kind = 'program_type'): string
    {
        if ($lang === 'ar') {
            return match ($kind) {
                'program_type' => 'أكيد، لأي نوع تدريب؟ Bodybuilding، MMA، أو Home Workout؟',
                'body_part_or_plan' => 'بدك تمرين لهالعضلة، ولا برنامج كامل؟',
                default => 'ممكن توضّح طلبك بجملة قصيرة؟',
            };
        }

        return match ($kind) {
            'program_type' => 'Sure — which training type: Bodybuilding, MMA, or Home Workout?',
            'body_part_or_plan' => 'Do you want exercises for that muscle, or a full program?',
            default => 'Could you clarify your request in one short sentence?',
        };
    }
}
