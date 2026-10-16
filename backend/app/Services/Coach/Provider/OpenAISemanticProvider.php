<?php

namespace App\Services\Coach\Provider;

use App\Services\Coach\Semantic\IntentOntology;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI structured semantic interpreter — Stage A only (never executes app logic).
 * Falls back to null on any failure so LocalSemanticProvider remains authoritative for execution.
 */
class OpenAISemanticProvider implements SemanticProviderInterface
{
    /** @var list<string> */
    private const ALLOWED_INTENTS = [
        'nutrition.lookup',
        'nutrition.quantity_lookup',
        'nutrition.calorie_question',
        'nutrition.protein_question',
        'nutrition.macro_question',
        'nutrition.plan_create',
        'nutrition.plan_modify',
        'nutrition.allergy_constraint',
        'training.exercise_recommendation',
        'training.body_part_request',
        'training.workout_create',
        'training.workout_modify',
        'training.workout_replace_exercise',
        'training.workout_change_difficulty',
        'training.workout_change_duration',
        'training.workout_change_equipment',
        'training.progression_question',
        'sports.general',
        'fitness.general',
        'plan.resume',
        'medical.safety',
        'injury.safety',
        'emergency.safety',
        'ambiguous_request',
        'unsupported_request',
        'prompt_injection',
    ];

    public function name(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        $keyPresent = trim((string) config('services.openai.api_key', '')) !== '';
        if (! $keyPresent) {
            return false;
        }

        // If a key is configured, enable unless explicitly disabled.
        $flag = config('services.openai.enabled');
        if ($flag === null) {
            return true;
        }

        return (bool) $flag;
    }

    public function interpret(
        string $message,
        bool $hasActivePlan = false,
        ?array $activePlanSummary = null,
        ?string $language = null,
        array $hints = [],
    ): ?array {
        if (! $this->isAvailable()) {
            return null;
        }

        $model = (string) config('services.openai.intent_model', config('services.openai.model', 'gpt-4o-mini'));
        $timeoutMs = (int) config('services.openai.timeout_ms', 8000);
        $timeoutSec = max(1, (int) ceil($timeoutMs / 1000));
        $apiKey = (string) config('services.openai.api_key');

        $system = $this->systemPrompt();
        // JSON object mode + allowlist validation (strict json_schema rejected free-form entity maps).
        $userPayload = [
            'message' => $message,
            'language_hint' => $language,
            'has_active_training_plan' => $hasActivePlan,
            'active_plan_summary' => $this->sanitizePlanSummary($activePlanSummary),
            'previous_topic' => $hints['previous_topic'] ?? null,
            'local_intent_hint' => $hints['local_intent'] ?? null,
            'local_confidence' => $hints['local_confidence'] ?? null,
        ];

        try {
            $verify = storage_path('certs/cacert.pem');
            $http = Http::withToken($apiKey)
                ->timeout($timeoutSec)
                ->acceptJson()
                ->asJson();
            if (is_file($verify)) {
                $http = $http->withOptions(['verify' => $verify]);
            }

            $response = $http->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => json_encode($userPayload, JSON_UNESCAPED_UNICODE)],
                ],
            ]);

            if (! $response->successful()) {
                $status = $response->status();
                Log::warning('coach.openai_interpret_http_failed', [
                    'status' => $status,
                    'rate_limited' => $status === 429,
                    'fallback' => 'local_semantic',
                ]);
                if ($status === 429) {
                    // Safe diagnostic metric only — never surface to the user.
                    Log::info('coach.openai_rate_limited', [
                        'fallback_provider' => 'local',
                        'pretend_openai_used' => false,
                    ]);
                }

                return null;
            }

            $content = data_get($response->json(), 'choices.0.message.content');
            if (! is_string($content) || trim($content) === '') {
                return null;
            }

            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                Log::warning('coach.openai_interpret_invalid_json');

                return null;
            }

            return $this->validateAndNormalize($decoded, $language);
        } catch (\Throwable $e) {
            Log::warning('coach.openai_interpret_unavailable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function validateAndNormalize(array $raw, ?string $language): ?array
    {
        $intent = (string) ($raw['intent'] ?? '');
        if ($intent === '' || ! IntentOntology::isAllowed($intent) || ! in_array($intent, self::ALLOWED_INTENTS, true)) {
            Log::warning('coach.openai_intent_not_allowlisted', ['intent' => $intent]);

            return null;
        }

        $canonical = IntentOntology::canonicalize($intent);
        $domain = IntentOntology::domainFor($canonical);
        $lang = in_array($raw['language'] ?? null, ['en', 'ar'], true)
            ? $raw['language']
            : (in_array($language, ['en', 'ar'], true) ? $language : 'en');

        $entities = is_array($raw['entities'] ?? null) ? $raw['entities'] : [];
        $constraints = is_array($raw['constraints'] ?? null) ? $raw['constraints'] : [];

        // Flatten common top-level fields into constraints/entities.
        foreach (['discipline', 'environment', 'goal', 'body_part', 'level'] as $k) {
            if (isset($raw[$k]) && $raw[$k] !== null && $raw[$k] !== '') {
                $entities[$k] = $raw[$k];
                if (in_array($k, ['discipline', 'goal', 'level'], true)) {
                    $constraints[$k] = $raw[$k];
                }
                if ($k === 'environment') {
                    $constraints['equipment_mode'] = $raw[$k] === 'gym' ? 'gym' : ($raw[$k] === 'home' ? 'home' : null);
                    $entities['equipment_mode'] = $constraints['equipment_mode'];
                }
            }
        }
        foreach (['days', 'duration_minutes'] as $k) {
            if (isset($raw[$k]) && is_numeric($raw[$k])) {
                $constraints[$k === 'days' ? 'days_per_week' : $k] = (int) $raw[$k];
            }
        }

        $isFollowUp = (bool) ($raw['is_follow_up'] ?? false);
        $capabilities = match ($canonical) {
            'nutrition_lookup' => ['nutrition_lookup'],
            'nutrition_plan_create' => ['nutrition_plan'],
            'training_plan_create' => ['training_plan'],
            'training_plan_modify', 'training_plan_resume' => ['training_modification'],
            'training_exercise_recommendation' => ['grounded_exercises'],
            'general_sports_knowledge' => ['rag_knowledge'],
            'safety' => ['safety'],
            'ambiguous_request' => [],
            default => [],
        };

        return [
            'intent' => $canonical,
            'domain' => $domain,
            'sub_intent' => isset($raw['operation']) ? (string) $raw['operation'] : ($raw['sub_intent'] ?? null),
            'operation' => [
                'type' => match ($canonical) {
                    'training_plan_create', 'nutrition_plan_create' => 'create',
                    'training_plan_modify' => 'modify',
                    'nutrition_lookup' => 'lookup',
                    default => 'explain',
                },
                'target' => null,
                'modification' => $isFollowUp ? ($raw['modification'] ?? null) : null,
            ],
            'entities' => $entities,
            'constraints' => array_filter($constraints, fn ($v) => $v !== null && $v !== ''),
            'context_requirements' => [
                'active_plan' => $isFollowUp || $canonical === 'training_plan_modify',
                'profile' => in_array($canonical, ['training_plan_create', 'nutrition_plan_create'], true)
                    && empty($constraints['discipline'] ?? null),
                'nutrition_data' => $canonical === 'nutrition_lookup',
                'knowledge' => $canonical === 'general_sports_knowledge',
            ],
            'capabilities' => $capabilities,
            'response_mode' => match ($canonical) {
                'nutrition_lookup' => 'direct',
                'training_plan_create', 'nutrition_plan_create', 'training_plan_modify' => 'structured',
                'ambiguous_request' => 'clarification',
                default => 'concise',
            },
            'confidence' => min(1.0, max(0.0, (float) ($raw['confidence'] ?? 0.7))),
            'language' => $lang,
            'provider' => 'openai',
            'is_follow_up' => $isFollowUp,
            'is_topic_switch' => (bool) ($raw['is_topic_switch'] ?? false),
            'clarification_required' => (bool) ($raw['clarification_required'] ?? false)
                || $canonical === 'ambiguous_request',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'intent' => ['type' => 'string', 'enum' => self::ALLOWED_INTENTS],
                'domain' => ['type' => 'string', 'enum' => ['training', 'nutrition', 'sports', 'medical', 'system']],
                'operation' => ['type' => ['string', 'null']],
                'confidence' => ['type' => 'number'],
                'is_follow_up' => ['type' => 'boolean'],
                'is_topic_switch' => ['type' => 'boolean'],
                'clarification_required' => ['type' => 'boolean'],
                'discipline' => ['type' => ['string', 'null']],
                'environment' => ['type' => ['string', 'null']],
                'goal' => ['type' => ['string', 'null']],
                'days' => ['type' => ['integer', 'null']],
                'duration_minutes' => ['type' => ['integer', 'null']],
                'body_part' => ['type' => ['string', 'null']],
                'level' => ['type' => ['string', 'null']],
                'modification' => ['type' => ['string', 'null']],
                'language' => ['type' => 'string', 'enum' => ['en', 'ar']],
                'entities' => ['type' => 'object', 'additionalProperties' => true],
                'constraints' => ['type' => 'object', 'additionalProperties' => true],
            ],
            'required' => [
                'intent', 'domain', 'operation', 'confidence', 'is_follow_up', 'is_topic_switch',
                'clarification_required', 'discipline', 'environment', 'goal', 'days',
                'duration_minutes', 'body_part', 'level', 'modification', 'language',
                'entities', 'constraints',
            ],
        ];
    }

    private function systemPrompt(): string
    {
        $allowed = implode(', ', self::ALLOWED_INTENTS);

        return <<<PROMPT
You are OCTA FIT Coach Stage-A semantic interpreter. Classify the CURRENT user message only.
Return ONLY a JSON object with keys:
intent, domain, operation, confidence, is_follow_up, is_topic_switch, clarification_required,
discipline, environment, goal, days, duration_minutes, body_part, level, modification, language,
entities (object), constraints (object).
intent MUST be one of: {$allowed}
Do NOT answer the user. Do NOT invent exercises, foods, IDs, or plans.
Priority: current message intent > previous topic. Mark is_topic_switch when domain changes vs previous_topic.
Mark is_follow_up only for modifications of an existing plan (harder day, replace exercise, change duration).
Use ambiguous_request when "I want a program" has no discipline/environment.
Support Syrian/Levantine Arabic, MSA, English, and mixed language.
PROMPT;
    }

    /**
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>|null
     */
    private function sanitizePlanSummary(?array $summary): ?array
    {
        if ($summary === null) {
            return null;
        }

        return [
            'discipline' => $summary['discipline'] ?? null,
            'days_per_week' => $summary['days_per_week'] ?? null,
            'session_duration_minutes' => $summary['session_duration_minutes'] ?? null,
        ];
    }
}
