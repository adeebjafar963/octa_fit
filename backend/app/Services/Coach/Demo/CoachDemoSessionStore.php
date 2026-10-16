<?php

namespace App\Services\Coach\Demo;

use Illuminate\Support\Facades\Cache;

/**
 * Conversation-scoped state for graduation demo follow-ups only.
 * Isolated from production CoachPlanContextStore semantics.
 */
class CoachDemoSessionStore
{
    private const TTL_SECONDS = 14400;

    public function remember(int $conversationId, array $state): void
    {
        Cache::put($this->key($conversationId), array_merge($state, [
            'saved_at' => now()->toIso8601String(),
        ]), self::TTL_SECONDS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recall(int $conversationId): ?array
    {
        $payload = Cache::get($this->key($conversationId));

        return is_array($payload) ? $payload : null;
    }

    public function forget(int $conversationId): void
    {
        Cache::forget($this->key($conversationId));
    }

    private function key(int $conversationId): string
    {
        return 'coach.graduation_demo.session.'.$conversationId;
    }
}
