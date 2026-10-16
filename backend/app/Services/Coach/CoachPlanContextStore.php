<?php

namespace App\Services\Coach;

use Illuminate\Support\Facades\Cache;

/**
 * Session-scoped active training plan context for conversational follow-ups.
 * Does not replace durable Coach memory (11H).
 */
class CoachPlanContextStore
{
    private const TTL_SECONDS = 7200;

    public function remember(int $conversationId, array $plan, array $intent = []): void
    {
        Cache::put($this->key($conversationId), [
            'plan' => $plan,
            'intent' => $intent,
            'saved_at' => now()->toIso8601String(),
        ], self::TTL_SECONDS);
    }

    /**
     * @return array{plan: array<string, mixed>, intent: array<string, mixed>}|null
     */
    public function recall(int $conversationId): ?array
    {
        $payload = Cache::get($this->key($conversationId));
        if (! is_array($payload) || ! isset($payload['plan']) || ! is_array($payload['plan'])) {
            return null;
        }

        return [
            'plan' => $payload['plan'],
            'intent' => is_array($payload['intent'] ?? null) ? $payload['intent'] : [],
        ];
    }

    public function forget(int $conversationId): void
    {
        Cache::forget($this->key($conversationId));
    }

    private function key(int $conversationId): string
    {
        return 'coach.active_training_plan.'.$conversationId;
    }
}
