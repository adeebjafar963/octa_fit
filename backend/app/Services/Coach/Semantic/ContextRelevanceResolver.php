<?php

namespace App\Services\Coach\Semantic;

/**
 * Decides which context slices are relevant AFTER current-message intent is known.
 * Authority: current request > follow-up plan > profile > memory > prior topic.
 */
class ContextRelevanceResolver
{
    /**
     * @param  array{
     *   decision: string,
     *   current_topic: string,
     *   previous_topic: string|null,
     *   is_follow_up: bool,
     *   is_topic_switch: bool
     * }  $topicDecision
     * @param  array{plan: array<string, mixed>, intent: array<string, mixed>}|null  $activePlan
     * @return array{
     *   use_active_plan: bool,
     *   use_profile: bool,
     *   use_memory: bool,
     *   use_prior_topic: bool,
     *   reason: string
     * }
     */
    public function resolve(SemanticIntentContract $intent, array $topicDecision, ?array $activePlan): array
    {
        $isFollowUp = ($topicDecision['is_follow_up'] ?? false) === true
            || $intent->intent === 'training_plan_modify'
            || $intent->intent === 'training_plan_resume'
            || $intent->needsActivePlan();

        $isTopicSwitch = ($topicDecision['is_topic_switch'] ?? false) === true;
        $isFood = $intent->intent === 'nutrition_lookup';
        $isKnowledge = $intent->intent === 'general_sports_knowledge';
        $isExerciseRec = $intent->intent === 'training_exercise_recommendation';
        $isAmbiguous = $intent->intent === 'ambiguous_request';
        $isSafety = $intent->domain === 'safety' || $intent->intent === 'safety';

        // Active plan ONLY for follow-ups / explicit plan reference — never on topic switch or facts.
        $useActivePlan = $isFollowUp
            && ! $isTopicSwitch
            && ! $isFood
            && ! $isKnowledge
            && $activePlan !== null;

        // Profile: personalization for plan create / modify, not for facts or simple exercise asks
        // unless the message explicitly asks to personalize.
        $explicitPersonalize = (bool) ($intent->contextRequirements['profile'] ?? false)
            && in_array($intent->intent, ['training_plan_create', 'nutrition_plan_create'], true);

        $useProfile = ! $isFood
            && ! $isKnowledge
            && ! $isSafety
            && ! $isAmbiguous
            && ! $isTopicSwitch
            && (
                $explicitPersonalize
                || $intent->intent === 'training_plan_create'
                || $intent->intent === 'nutrition_plan_create'
                || ($isFollowUp && $useActivePlan)
            );

        // On topic switch into a NEW plan create, profile fills gaps only (still allowed lightly).
        if ($isTopicSwitch && $intent->intent === 'training_plan_create') {
            $useProfile = true;
            $useActivePlan = false;
        }

        if ($isExerciseRec) {
            $useProfile = false;
            $useActivePlan = false;
        }

        $useMemory = $useProfile && ! $isFood && ! $isKnowledge;
        $usePriorTopic = ! $isTopicSwitch && ($isFollowUp || ($topicDecision['decision'] ?? '') === TopicSwitchDetector::SAME_TOPIC);

        $reason = match (true) {
            $isSafety => 'safety_minimal',
            $isFood => 'direct_factual',
            $isKnowledge => 'general_knowledge',
            $isTopicSwitch => 'topic_switch_current_only',
            $isFollowUp && $useActivePlan => 'follow_up_active_plan',
            $isExerciseRec => 'exercise_recommendation',
            $isAmbiguous => 'clarification',
            default => 'standard',
        };

        return [
            'use_active_plan' => $useActivePlan,
            'use_profile' => $useProfile,
            'use_memory' => $useMemory,
            'use_prior_topic' => $usePriorTopic,
            'reason' => $reason,
        ];
    }
}
