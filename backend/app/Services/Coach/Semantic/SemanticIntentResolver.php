<?php

namespace App\Services\Coach\Semantic;

use App\Services\Coach\Provider\LocalSemanticProvider;
use App\Services\Coach\Provider\OpenAISemanticProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves semantic intent: local first → optional OpenAI when uncertain → FastAPI /interpret.
 * CURRENT MESSAGE is always classified independently of prior topic dominance.
 */
class SemanticIntentResolver
{
    private const LOCAL_HIGH_CONFIDENCE = 0.78;

    public function __construct(
        private readonly DeterministicEntityExtractor $entityExtractor,
        private readonly LocalSemanticInterpreter $localInterpreter,
        private readonly LocalSemanticProvider $localProvider,
        private readonly OpenAISemanticProvider $openAIProvider,
    ) {}

    /**
     * @param  array<string, mixed>|null  $activePlanSummary
     * @param  array<string, mixed>  $hints
     */
    public function resolve(
        string $message,
        bool $hasActivePlan = false,
        ?array $activePlanSummary = null,
        ?string $language = null,
        array $hints = [],
    ): SemanticIntentContract {
        // Always run local first (deterministic + ontology).
        $localRaw = $this->localProvider->interpret($message, $hasActivePlan, $activePlanSummary, $language, $hints);
        $localConfidence = (float) ($localRaw['confidence'] ?? 0.5);
        $localIntent = (string) ($localRaw['intent'] ?? 'general_conversation');

        $useOpenAI = $this->shouldUseOpenAI($localConfidence, $localIntent, $message, $hints);
        $remote = null;
        $provider = 'local';

        if ($useOpenAI && $this->openAIProvider->isAvailable() && ! app()->environment('testing')) {
            $remote = $this->openAIProvider->interpret(
                $message,
                $hasActivePlan,
                $activePlanSummary,
                $language,
                array_merge($hints, [
                    'local_intent' => $localIntent,
                    'local_confidence' => $localConfidence,
                ]),
            );
            if (is_array($remote)) {
                $provider = 'openai';
            }
        }

        // FastAPI /interpret as secondary remote when OpenAI unused/unavailable (non-testing).
        if ($remote === null && ! app()->environment('testing') && $this->interpretApiConfigured()) {
            $fastapi = $this->callInterpretApi($message, $hasActivePlan, $activePlanSummary, $language);
            if (is_array($fastapi)) {
                $remote = $fastapi;
                $provider = 'fastapi';
            }
        }

        // Prefer OpenAI/FastAPI only when they don't contradict a high-confidence local hard fact
        // (nutrition lookup / safety / clear plan create).
        $raw = $localRaw;
        if (is_array($remote)) {
            $remoteIntent = IntentOntology::canonicalize((string) ($remote['intent'] ?? ''));
            if ($this->shouldPreferRemote($localIntent, $localConfidence, $remoteIntent, (float) ($remote['confidence'] ?? 0))) {
                $raw = $remote;
                $raw['intent'] = $remoteIntent;
                $raw['domain'] = IntentOntology::domainFor($remoteIntent);
            } else {
                $provider = 'local';
            }
        }

        $raw['provider'] = $provider;
        $contract = SemanticIntentContract::fromArray($raw);
        $contract = $this->mergeDeterministicEntities($contract, $message);

        Log::info('coach.semantic_intent', [
            'intent' => $contract->intent,
            'domain' => $contract->domain,
            'sub_intent' => $contract->subIntent,
            'modification' => $contract->modification(),
            'target_day' => $contract->targetDay(),
            'confidence' => $contract->confidence,
            'provider' => $provider,
            'is_follow_up' => $raw['is_follow_up'] ?? null,
            'is_topic_switch' => $raw['is_topic_switch'] ?? null,
        ]);

        return $contract;
    }

    /**
     * Diagnostic snapshot (no secrets).
     *
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        $openai = $this->openAIProvider->isAvailable();

        return [
            'provider' => $openai ? 'openai' : 'local',
            'model' => $openai
                ? (string) config('services.openai.intent_model', config('services.openai.model'))
                : 'local_ontology',
            'semantic_interpreter' => $openai ? 'enabled' : 'disabled',
            'openai_enabled_flag' => (bool) config('services.openai.enabled', false),
            'openai_key_present' => trim((string) config('services.openai.api_key', '')) !== '',
            'fastapi_interpret' => $this->interpretApiConfigured(),
        ];
    }

    private function shouldUseOpenAI(float $localConfidence, string $localIntent, string $message, array $hints): bool
    {
        if (! $this->openAIProvider->isAvailable()) {
            return false;
        }
        // Hard local facts — skip OpenAI.
        if (in_array($localIntent, ['nutrition_lookup', 'safety'], true) && $localConfidence >= 0.8) {
            return false;
        }
        if ($localConfidence >= self::LOCAL_HIGH_CONFIDENCE
            && in_array($localIntent, [
                'training_plan_create',
                'nutrition_plan_create',
                'nutrition_plan_modify',
                'training_exercise_recommendation',
                'training_plan_modify',
                'memory_related',
                'exercise_lookup',
                'general_sports_knowledge',
            ], true)) {
            return false;
        }
        // Prefer OpenAI for general_conversation even when local returns something.
        if (in_array($localIntent, ['ambiguous_request', 'general_conversation'], true)
            || $localConfidence < self::LOCAL_HIGH_CONFIDENCE) {
            return true;
        }
        if (($hints['topic_uncertain'] ?? false) === true) {
            return true;
        }
        // Mixed language / novel colloquial
        $hasArabic = (bool) preg_match('/\p{Arabic}/u', $message);
        $hasLatin = (bool) preg_match('/[A-Za-z]{3,}/', $message);

        return $hasArabic && $hasLatin;
    }

    private function shouldPreferRemote(
        string $localIntent,
        float $localConfidence,
        string $remoteIntent,
        float $remoteConfidence,
    ): bool {
        if ($remoteIntent === '' || $remoteConfidence < 0.55) {
            return false;
        }
        // Never override high-confidence safety / nutrition lookup.
        if (in_array($localIntent, ['safety', 'nutrition_lookup'], true) && $localConfidence >= 0.85) {
            return false;
        }
        // Prefer remote when local collapsed to generic conversation.
        if ($localIntent === 'general_conversation' && $remoteIntent !== 'general_conversation') {
            return true;
        }
        if ($remoteConfidence >= $localConfidence) {
            return true;
        }
        // Remote clarifies ambiguity.
        if ($localIntent === 'ambiguous_request' && $remoteIntent !== 'ambiguous_request') {
            return true;
        }

        return false;
    }

    private function interpretApiConfigured(): bool
    {
        return rtrim((string) config('services.ai.coach_url'), '/') !== '';
    }

    /**
     * @param  array<string, mixed>|null  $activePlanSummary
     * @return array<string, mixed>|null
     */
    private function callInterpretApi(
        string $message,
        bool $hasActivePlan,
        ?array $activePlanSummary,
        ?string $language,
    ): ?array {
        $baseUrl = rtrim((string) config('services.ai.coach_url'), '/');
        if ($baseUrl === '') {
            return null;
        }

        $token = (string) config('services.ai.internal_token');
        $payload = [
            'message' => $message,
            'has_active_training_plan' => $hasActivePlan,
            'active_plan_summary' => $activePlanSummary,
        ];
        if ($language !== null && in_array($language, ['en', 'ar'], true)) {
            $payload['language'] = $language;
        }

        try {
            $request = Http::timeout(12)->acceptJson()->asJson();
            if ($token !== '') {
                $request = $request->withHeaders(['X-Internal-Token' => $token]);
            }
            $response = $request->post("{$baseUrl}/interpret", $payload);
            if (! $response->successful()) {
                Log::warning('coach.interpret_http_failed', ['status' => $response->status()]);

                return null;
            }
            $data = $response->json();
            if (! is_array($data)) {
                return null;
            }
            $data['intent'] = IntentOntology::canonicalize((string) ($data['intent'] ?? 'general_conversation'));
            $data['domain'] = IntentOntology::domainFor($data['intent']);

            return $data;
        } catch (\Throwable $e) {
            Log::warning('coach.interpret_unavailable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function mergeDeterministicEntities(SemanticIntentContract $contract, string $message): SemanticIntentContract
    {
        $det = $this->entityExtractor->extract($message);
        $entities = $contract->entities;
        $constraints = $contract->constraints;

        if ($det['day'] !== null) {
            $entities['day'] = $det['day'];
        }
        if (($det['body_part'] ?? null) !== null) {
            $entities['body_part'] = $det['body_part'];
            $constraints['body_part'] = $det['body_part'];
        }
        foreach (['duration_minutes', 'days_per_week', 'quantity_grams', 'discipline', 'equipment_mode', 'goal'] as $key) {
            if ($det[$key] !== null) {
                // Do not inject training discipline from diet-context messages.
                if ($key === 'discipline' && ($det['is_nutrition_diet_context'] ?? false)) {
                    continue;
                }
                $constraints[$key] = $det[$key];
                if (in_array($key, ['discipline', 'equipment_mode', 'goal', 'body_part'], true)) {
                    $entities[$key] = $det[$key];
                }
            }
        }

        $operation = $contract->operation;
        if (is_array($operation) && ($operation['modification'] ?? '') === 'increase_difficulty' && $det['day'] !== null) {
            $operation['target'] = ['type' => 'day', 'index' => $det['day'], 'reference' => null];
        }

        return new SemanticIntentContract(
            intent: IntentOntology::canonicalize($contract->intent),
            domain: IntentOntology::domainFor(IntentOntology::canonicalize($contract->intent)),
            subIntent: $contract->subIntent,
            operation: $operation,
            entities: $entities,
            constraints: $constraints,
            contextRequirements: $contract->contextRequirements,
            capabilities: $contract->capabilities,
            responseMode: $contract->responseMode,
            confidence: $contract->confidence,
            language: $contract->language,
        );
    }
}
