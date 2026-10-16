<?php

namespace App\Services\Coach\Semantic;

use Illuminate\Support\Facades\Cache;

/**
 * Lightweight per-conversation topic stack (not durable memory).
 */
class ConversationTopicState
{
    private const TTL_SECONDS = 7200;

    private const MAX_HISTORY = 12;

    /**
     * @return array{active_topic: string|null, history: list<string>}
     */
    public function recall(int $conversationId): array
    {
        $payload = Cache::get($this->key($conversationId));
        if (! is_array($payload)) {
            return ['active_topic' => null, 'history' => []];
        }

        return [
            'active_topic' => isset($payload['active_topic']) ? (string) $payload['active_topic'] : null,
            'history' => array_values(array_filter(
                is_array($payload['history'] ?? null) ? $payload['history'] : [],
                fn ($t) => is_string($t) && $t !== '',
            )),
        ];
    }

    public function push(int $conversationId, string $topic): void
    {
        $state = $this->recall($conversationId);
        $history = $state['history'];
        if ($state['active_topic'] !== $topic) {
            $history[] = $topic;
            if (count($history) > self::MAX_HISTORY) {
                $history = array_slice($history, -self::MAX_HISTORY);
            }
        }

        Cache::put($this->key($conversationId), [
            'active_topic' => $topic,
            'history' => $history,
            'updated_at' => now()->toIso8601String(),
        ], self::TTL_SECONDS);
    }

    public function forget(int $conversationId): void
    {
        Cache::forget($this->key($conversationId));
    }

    private function key(int $conversationId): string
    {
        return 'coach.conversation_topic.'.$conversationId;
    }
}
