<?php

namespace App\Services\Coach\Provider;

/**
 * Provider-agnostic semantic interpretation contract.
 *
 * @phpstan-type InterpretResult array{
 *   intent: string,
 *   domain: string,
 *   sub_intent: string|null,
 *   operation: array<string, mixed>|null,
 *   entities: array<string, mixed>,
 *   constraints: array<string, mixed>,
 *   context_requirements: array<string, bool>,
 *   capabilities: list<string>,
 *   response_mode: string,
 *   confidence: float,
 *   language: string,
 *   provider: string,
 *   is_follow_up?: bool,
 *   is_topic_switch?: bool,
 *   clarification_required?: bool
 * }
 */
interface SemanticProviderInterface
{
    /**
     * @param  array<string, mixed>|null  $activePlanSummary
     * @param  array<string, mixed>  $hints  prior topic / local confidence hints (no secrets)
     * @return InterpretResult|null  null = provider unavailable / invalid
     */
    public function interpret(
        string $message,
        bool $hasActivePlan = false,
        ?array $activePlanSummary = null,
        ?string $language = null,
        array $hints = [],
    ): ?array;

    public function name(): string;

    public function isAvailable(): bool;
}
