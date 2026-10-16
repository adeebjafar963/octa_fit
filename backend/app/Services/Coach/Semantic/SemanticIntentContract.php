<?php

namespace App\Services\Coach\Semantic;

/**
 * Canonical internal representation of user intent (never exposed to clients).
 *
 * @phpstan-type OperationTarget array{type: string|null, index: int|null, reference: string|null}
 * @phpstan-type OperationSpec array{type: string|null, target: OperationTarget|null, modification: string|null}
 */
class SemanticIntentContract
{
    /**
     * @param  array<string, mixed>  $entities
     * @param  array<string, mixed>  $constraints
     * @param  array<string, bool>  $contextRequirements
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public readonly string $intent,
        public readonly string $domain,
        public readonly ?string $subIntent,
        public readonly ?array $operation,
        public readonly array $entities,
        public readonly array $constraints,
        public readonly array $contextRequirements,
        public readonly array $capabilities,
        public readonly string $responseMode,
        public readonly float $confidence,
        public readonly string $language,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $ctx = $data['context_requirements'] ?? [];
        if (! is_array($ctx)) {
            $ctx = [];
        }

        return new self(
            intent: (string) ($data['intent'] ?? 'general_conversation'),
            domain: (string) ($data['domain'] ?? 'conversation'),
            subIntent: isset($data['sub_intent']) ? (string) $data['sub_intent'] : null,
            operation: is_array($data['operation'] ?? null) ? $data['operation'] : null,
            entities: is_array($data['entities'] ?? null) ? $data['entities'] : [],
            constraints: is_array($data['constraints'] ?? null) ? $data['constraints'] : [],
            contextRequirements: $ctx,
            capabilities: is_array($data['capabilities'] ?? null) ? array_values($data['capabilities']) : [],
            responseMode: (string) ($data['response_mode'] ?? 'concise'),
            confidence: (float) ($data['confidence'] ?? 0.5),
            language: in_array($data['language'] ?? null, ['en', 'ar'], true) ? $data['language'] : 'en',
        );
    }

    public function modification(): ?string
    {
        return is_array($this->operation) ? ($this->operation['modification'] ?? null) : null;
    }

    public function targetDay(): ?int
    {
        if (! is_array($this->operation) || ! is_array($this->operation['target'] ?? null)) {
            return isset($this->entities['day']) ? (int) $this->entities['day'] : null;
        }

        $idx = $this->operation['target']['index'] ?? null;

        return $idx !== null ? (int) $idx : null;
    }

    public function needsActivePlan(): bool
    {
        return (bool) ($this->contextRequirements['active_plan'] ?? false);
    }
}
