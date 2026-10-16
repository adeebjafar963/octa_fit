<?php

namespace App\Services\Coach;

use App\Services\Coach\Semantic\CapabilityRouter;
use App\Services\Coach\Semantic\SemanticIntentResolver;

/**
 * Detect conversational follow-ups against an active plan context.
 * Uses semantic NLU; deterministic day extraction as authority for indices.
 */
class CoachFollowUpInterpreter
{
    public function __construct(
        private readonly SemanticIntentResolver $intentResolver,
        private readonly CapabilityRouter $capabilityRouter,
    ) {}

    /**
     * @param  array{plan: array<string, mixed>, intent: array<string, mixed>}  $active
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
    public function interpret(string $message, array $active): array
    {
        $intent = $active['intent'] ?? [];
        $empty = [
            'is_follow_up' => false,
            'action' => null,
            'duration_minutes' => null,
            'day' => null,
            'remove_equipment' => null,
            'target_exercise' => null,
            'merged_intent' => $intent,
        ];

        if (trim($message) === '' || ($active['plan'] ?? null) === null) {
            return $empty;
        }

        $summary = [
            'discipline' => $active['plan']['discipline'] ?? null,
            'days_per_week' => $active['plan']['days_per_week'] ?? null,
            'session_duration_minutes' => $active['plan']['requested_session_duration_minutes'] ?? null,
        ];

        $semantic = $this->intentResolver->resolve($message, true, $summary);
        $followUp = $this->capabilityRouter->toFollowUp($semantic, $intent);

        if ($followUp['is_follow_up']) {
            return $followUp;
        }

        // Equipment removal — deterministic safety pattern (not general NLU).
        $lower = mb_strtolower(trim($message));
        if (preg_match('/(don\'t have|do not have|without|ما عندي|بدون).{0,40}(heavy bag|bag|dumbbells?|barbell|bench|kettlebell|بار|حقيبة|دمبل)/iu', $lower, $m)) {
            return [
                'is_follow_up' => true,
                'action' => 'exclude_equipment',
                'duration_minutes' => null,
                'day' => null,
                'remove_equipment' => mb_strtolower((string) ($m[2] ?? 'equipment')),
                'target_exercise' => null,
                'merged_intent' => array_merge($intent, ['is_plan_request' => true]),
            ];
        }

        return $empty;
    }
}
