<?php

namespace App\Services\Coach\Semantic;

use App\Services\Coach\Response\CoachResponsePolicy;
use Illuminate\Support\Facades\Log;

/**
 * Central intelligence: CURRENT MESSAGE → intent → topic decision → conditional context → route.
 */
class SemanticOrchestrator
{
    public function __construct(
        private readonly SemanticIntentResolver $intentResolver,
        private readonly ConstraintResolver $constraintResolver,
        private readonly ContextSelector $contextSelector,
        private readonly CapabilityRouter $capabilityRouter,
        private readonly TopicSwitchDetector $topicSwitchDetector,
        private readonly ContextRelevanceResolver $contextRelevance,
        private readonly CoachResponsePolicy $responsePolicy,
        private readonly ConversationTopicState $topicState,
        private readonly MultiIntentDetector $multiIntentDetector,
    ) {}

    /**
     * @param  array<string, mixed>  $userContext
     * @param  array{plan: array<string, mixed>, intent: array<string, mixed>}|null  $activePlan
     * @return array{
     *   routing: array<string, mixed>,
     *   follow_up: array<string, mixed>,
     *   semantic: SemanticIntentContract,
     *   training_intent: array<string, mixed>,
     *   user_context: array<string, mixed>,
     *   topic_decision: array<string, mixed>,
     *   context_relevance: array<string, mixed>,
     *   response_policy: array<string, mixed>,
     *   active_plan_for_engines: array{plan: array<string, mixed>, intent: array<string, mixed>}|null
     * }
     */
    public function orchestrate(
        string $message,
        array $userContext,
        ?array $activePlan = null,
        ?int $conversationId = null,
    ): array {
        $topicState = $conversationId !== null
            ? $this->topicState->recall($conversationId)
            : ['active_topic' => null, 'history' => []];

        // Classify CURRENT message with hasActivePlan=false first for topic-switch purity,
        // then re-resolve modification only if topic detector says follow-up.
        $semanticCurrent = $this->intentResolver->resolve(
            $message,
            false,
            null,
            $userContext['language'] ?? null,
            [
                'previous_topic' => $topicState['active_topic'],
            ],
        );

        $semanticSaysFollowUp = $semanticCurrent->intent === 'training_plan_modify'
            || $semanticCurrent->intent === 'training_plan_resume'
            || $semanticCurrent->needsActivePlan();

        // If local already saw follow-up cues but we hid active plan, re-resolve WITH plan summary.
        $hasStoredPlan = $activePlan !== null && ($activePlan['plan'] ?? null) !== null;
        if ($hasStoredPlan && ! $semanticSaysFollowUp) {
            // Probe with active plan available for modification detection only.
            $withPlan = $this->intentResolver->resolve(
                $message,
                true,
                [
                    'discipline' => $activePlan['plan']['discipline'] ?? null,
                    'days_per_week' => $activePlan['plan']['days_per_week'] ?? null,
                    'session_duration_minutes' => $activePlan['plan']['requested_session_duration_minutes'] ?? null,
                ],
                $userContext['language'] ?? null,
                ['previous_topic' => $topicState['active_topic']],
            );
            if (in_array($withPlan->intent, ['training_plan_modify', 'training_plan_resume'], true)) {
                $semanticCurrent = $withPlan;
                $semanticSaysFollowUp = true;
            }
        }

        $topicDecision = $this->topicSwitchDetector->detect(
            $semanticCurrent,
            $topicState['active_topic'],
            $topicState['history'],
            $semanticSaysFollowUp,
        );

        // On hard topic switch, never treat as follow-up even if active plan exists.
        if (($topicDecision['is_topic_switch'] ?? false) === true) {
            $semanticSaysFollowUp = false;
            // Re-classify without active plan if we accidentally took a modify path.
            if (in_array($semanticCurrent->intent, ['training_plan_modify', 'training_plan_resume'], true)
                && IntentOntology::topicBucket($semanticCurrent->intent, $semanticCurrent->domain) !== ($topicState['active_topic'] ?? '')) {
                // keep as classified from first pass without plan
            }
        }

        $relevance = $this->contextRelevance->resolve($semanticCurrent, $topicDecision, $activePlan);
        $activeForEngines = ($relevance['use_active_plan'] ?? false) ? $activePlan : null;

        $forcePlan = $semanticCurrent->intent === 'training_plan_create'
            || in_array('training_plan', $semanticCurrent->capabilities, true);

        // Multi-intent: if nutrition was primary but message is a training+diet compound, flip primary to training.
        if ($semanticCurrent->intent === 'nutrition_plan_create'
            && preg_match('/(كمال|bodybuilding|mma|برنامج|تدريب|gym|جيم|حديد)/u', mb_strtolower($message))
            && preg_match('/(نظام|diet|meal|غذا|تنشيف|تضخيم|وجبات)/u', mb_strtolower($message))
            && preg_match('/(\d)\s*(ايام|أيام|days?)|يوم/u', mb_strtolower($message))
        ) {
            $forcePlan = true;
        }

        // Active intent merges ONLY when follow-up; never on topic switch.
        $activeIntent = ($relevance['use_active_plan'] ?? false) ? ($activePlan['intent'] ?? []) : null;

        $trainingIntent = $this->constraintResolver->resolveTrainingIntent(
            $message,
            ($relevance['use_profile'] ?? false) ? $userContext : $this->minimalContext($userContext),
            $semanticCurrent->constraints,
            $activeIntent,
            $forcePlan,
        );

        // On topic switch into training, strip inherited discipline from old nutrition goals.
        if (($topicDecision['is_topic_switch'] ?? false) === true
            && ($topicDecision['previous_topic'] ?? null) === 'nutrition'
            && $semanticCurrent->intent === 'training_plan_create'
        ) {
            // Keep only message-explicit fields.
            if (! ($trainingIntent['discipline_explicit'] ?? false)) {
                // equipment/gym from message may still set discipline via extractor
            }
        }

        $routing = $this->capabilityRouter->toRouting($semanticCurrent, $trainingIntent);
        $routing['is_topic_switch'] = $topicDecision['is_topic_switch'] ?? false;
        $routing['is_follow_up'] = $topicDecision['is_follow_up'] ?? false;
        $routing['topic_decision'] = $topicDecision['decision'] ?? null;
        $routing['current_topic'] = $topicDecision['current_topic'] ?? null;
        $routing['previous_topic'] = $topicDecision['previous_topic'] ?? null;

        $multi = $this->multiIntentDetector->detect($message, $semanticCurrent);
        $routing['is_multi_intent'] = $multi['is_multi'];
        $routing['secondary_intents'] = $multi['intents'];
        if ($multi['is_multi']) {
            $routing['needs_nutrition_plan'] = $routing['needs_nutrition_plan']
                || in_array('nutrition_plan_create', $multi['intents'], true);
            $routing['is_nutrition_plan'] = $routing['is_nutrition_plan']
                || in_array('nutrition_plan_create', $multi['intents'], true);
            $routing['is_plan_request'] = $routing['is_plan_request']
                || in_array('training_plan_create', $multi['intents'], true);
            $routing['is_food_lookup'] = $routing['is_food_lookup']
                || in_array('nutrition_lookup', $multi['intents'], true);
            $routing['is_exercise_recommendation'] = $routing['is_exercise_recommendation']
                || in_array('training_exercise_recommendation', $multi['intents'], true);
            if ($routing['is_plan_request']) {
                $forcePlan = true;
                $trainingIntent['is_plan_request'] = true;
            }
        }

        $followUp = ($relevance['use_active_plan'] ?? false)
            ? $this->capabilityRouter->toFollowUp($semanticCurrent, $activeIntent ?? [])
            : ['is_follow_up' => false];

        if (($topicDecision['is_topic_switch'] ?? false) === true) {
            $followUp = ['is_follow_up' => false];
        }

        $selectedContext = $this->contextSelector->select(
            ($relevance['use_profile'] ?? true) ? $userContext : $this->minimalContext($userContext),
            $semanticCurrent,
        );

        $policy = $this->responsePolicy->forIntent($semanticCurrent, $topicDecision);

        if ($conversationId !== null && ($topicDecision['current_topic'] ?? null) !== null) {
            $this->topicState->push($conversationId, (string) $topicDecision['current_topic']);
        }

        Log::info('coach.capability_route', [
            'intent' => $semanticCurrent->intent,
            'capabilities' => $semanticCurrent->capabilities,
            'is_plan_request' => $routing['is_plan_request'] ?? false,
            'force_plan' => $forcePlan,
            'follow_up_action' => $followUp['action'] ?? null,
            'target_day' => $followUp['day'] ?? null,
            'is_topic_switch' => $topicDecision['is_topic_switch'] ?? false,
            'topic_decision' => $topicDecision['decision'] ?? null,
        ]);

        Log::info('coach.context_selection', [
            'skip_profile' => $routing['skip_profile'] ?? false,
            'use_active_plan' => $relevance['use_active_plan'] ?? false,
            'use_profile' => $relevance['use_profile'] ?? false,
            'relevance_reason' => $relevance['reason'] ?? null,
        ]);

        Log::info('coach.constraint_resolution', [
            'discipline' => $trainingIntent['discipline'] ?? null,
            'equipment_mode' => $trainingIntent['equipment_mode'] ?? null,
            'days' => $trainingIntent['days_per_week'] ?? null,
            'duration_minutes' => $trainingIntent['session_duration_minutes'] ?? null,
            'is_plan_request' => $trainingIntent['is_plan_request'] ?? false,
        ]);

        return [
            'routing' => $routing,
            'follow_up' => $followUp,
            'semantic' => $semanticCurrent,
            'training_intent' => $trainingIntent,
            'user_context' => $selectedContext,
            'topic_decision' => $topicDecision,
            'context_relevance' => $relevance,
            'response_policy' => $policy,
            'active_plan_for_engines' => $activeForEngines,
            'multi_intent' => $multi,
        ];
    }

    /**
     * @param  array<string, mixed>  $full
     * @return array<string, mixed>
     */
    private function minimalContext(array $full): array
    {
        return [
            'locale' => $full['locale'] ?? 'en',
            'language' => $full['language'] ?? 'en',
        ];
    }
}
