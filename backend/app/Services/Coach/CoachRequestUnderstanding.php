<?php

namespace App\Services\Coach;

use App\Services\Coach\Semantic\SemanticOrchestrator;

/**
 * Conversational request understanding — delegates to semantic orchestration layer.
 * Message-first; profile/memory are defaults only when not explicit.
 */
class CoachRequestUnderstanding
{
    public function __construct(
        private readonly SemanticOrchestrator $orchestrator,
    ) {}

    /**
     * @param  array<string, mixed>  $userContext
     * @return array<string, mixed>
     */
    public function understand(
        string $message,
        array $userContext = [],
        ?array $activePlan = null,
        ?int $conversationId = null,
    ): array {
        $result = $this->orchestrator->orchestrate($message, $userContext, $activePlan, $conversationId);

        return $result['routing'];
    }

    /**
     * @param  array<string, mixed>  $userContext
     * @param  array{plan: array<string, mixed>, intent: array<string, mixed>}|null  $activePlan
     * @return array{
     *   routing: array<string, mixed>,
     *   follow_up: array<string, mixed>,
     *   training_intent: array<string, mixed>,
     *   user_context: array<string, mixed>,
     *   topic_decision: array<string, mixed>,
     *   context_relevance: array<string, mixed>,
     *   response_policy: array<string, mixed>,
     *   active_plan_for_engines: array{plan: array<string, mixed>, intent: array<string, mixed>}|null,
     *   semantic: \App\Services\Coach\Semantic\SemanticIntentContract
     * }
     */
    public function understandFull(
        string $message,
        array $userContext = [],
        ?array $activePlan = null,
        ?int $conversationId = null,
    ): array {
        $result = $this->orchestrator->orchestrate($message, $userContext, $activePlan, $conversationId);

        return [
            'routing' => $result['routing'],
            'follow_up' => $result['follow_up'],
            'training_intent' => $result['training_intent'],
            'user_context' => $result['user_context'],
            'topic_decision' => $result['topic_decision'],
            'context_relevance' => $result['context_relevance'],
            'response_policy' => $result['response_policy'],
            'active_plan_for_engines' => $result['active_plan_for_engines'],
            'semantic' => $result['semantic'],
        ];
    }
}
