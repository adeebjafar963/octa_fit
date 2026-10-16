<?php

namespace App\Services\Coach\Provider;

use App\Services\Coach\Semantic\IntentOntology;
use App\Services\Coach\Semantic\LocalSemanticInterpreter;
use Illuminate\Support\Facades\Log;

/**
 * Local ontology-based semantic interpreter (always available fallback).
 */
class LocalSemanticProvider implements SemanticProviderInterface
{
    public function __construct(
        private readonly LocalSemanticInterpreter $interpreter,
    ) {}

    public function name(): string
    {
        return 'local';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function interpret(
        string $message,
        bool $hasActivePlan = false,
        ?array $activePlanSummary = null,
        ?string $language = null,
        array $hints = [],
    ): ?array {
        $raw = $this->interpreter->interpret($message, $hasActivePlan, $activePlanSummary);
        $raw['intent'] = IntentOntology::canonicalize((string) ($raw['intent'] ?? 'general_conversation'));
        $raw['domain'] = IntentOntology::domainFor($raw['intent']);
        $raw['provider'] = 'local';

        Log::debug('coach.semantic_provider', [
            'provider' => 'local',
            'intent' => $raw['intent'],
            'confidence' => $raw['confidence'] ?? null,
        ]);

        return $raw;
    }
}
