<?php

namespace App\Services;

use App\Models\CoachConversation;
use App\Models\CoachMessage;
use App\Models\User;
use App\Services\Coach\CoachExerciseRetrievalService;
use App\Services\Coach\CoachFollowUpInterpreter;
use App\Services\Coach\CoachPlanContextStore;
use App\Services\Coach\CoachRequestUnderstanding;
use App\Services\Coach\Demo\CoachDemoScenarioService;
use App\Services\Coach\Knowledge\KnowledgeIntentDetector;
use App\Services\Coach\Knowledge\KnowledgeRetrievalService;
use App\Services\Coach\Memory\CoachMemoryExtractor;
use App\Services\Coach\Memory\CoachMemoryService;
use App\Services\Coach\Nutrition\CalorieTargetCalculator;
use App\Services\Coach\Nutrition\MacroTargetCalculator;
use App\Services\Coach\Nutrition\NutritionLookupService;
use App\Services\Coach\Nutrition\NutritionPlanEngine;
use App\Services\Coach\Response\CoachResponsePolicy;
use App\Services\Coach\TrainingIntentExtractor;
use App\Services\Coach\TrainingPlanEngine;
use App\Services\Coach\Validation\CoachAnswerValidator;
use App\Models\CoachMemory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class CoachService
{
    private const CONTEXT_LIMIT = 16;

    public function __construct(
        private readonly UserAiContextBuilder $aiContextBuilder,
        private readonly CoachExerciseRetrievalService $exerciseRetrieval,
        private readonly TrainingPlanEngine $trainingPlanEngine,
        private readonly NutritionLookupService $nutritionLookup,
        private readonly NutritionPlanEngine $nutritionPlanEngine,
        private readonly KnowledgeRetrievalService $knowledgeRetrieval,
        private readonly KnowledgeIntentDetector $knowledgeIntent,
        private readonly CalorieTargetCalculator $calorieTargets,
        private readonly MacroTargetCalculator $macroTargets,
        private readonly CoachMemoryExtractor $memoryExtractor,
        private readonly CoachMemoryService $memoryService,
        private readonly TrainingIntentExtractor $trainingIntentExtractor,
        private readonly CoachPlanContextStore $planContextStore,
        private readonly CoachFollowUpInterpreter $followUpInterpreter,
        private readonly CoachRequestUnderstanding $requestUnderstanding,
        private readonly CoachAnswerValidator $answerValidator,
        private readonly CoachResponsePolicy $responsePolicy,
        private readonly CoachDemoScenarioService $demoScenarioService,
    ) {}

    public function listConversations(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return CoachConversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function createConversation(User $user, ?string $title = null): CoachConversation
    {
        $title = $title !== null ? trim($title) : null;
        if ($title === '') {
            $title = null;
        }

        return CoachConversation::query()->create([
            'user_id' => $user->id,
            'title' => $title,
        ]);
    }

    public function findForUser(User $user, int $conversationId): ?CoachConversation
    {
        return CoachConversation::query()
            ->where('user_id', $user->id)
            ->where('id', $conversationId)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at')->orderBy('id')])
            ->first();
    }

    public function deleteConversation(CoachConversation $conversation): void
    {
        $conversation->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMemories(User $user): array
    {
        return $this->memoryService->listActive($user)
            ->map(fn (CoachMemory $m) => $m->toApiArray())
            ->values()
            ->all();
    }

    public function deactivateMemory(User $user, CoachMemory $memory): CoachMemory
    {
        return $this->memoryService->deactivate($user, $memory);
    }

    public function deleteMemory(User $user, CoachMemory $memory): void
    {
        $this->memoryService->delete($user, $memory);
    }

    public function clearMemories(User $user): int
    {
        return $this->memoryService->clearAll($user);
    }

    /**
     * @return array{
     *   user_message: CoachMessage,
     *   assistant_message: CoachMessage,
     *   conversation: CoachConversation,
     *   grounded_exercises: list<array<string, mixed>>,
     *   training_plan: array<string, mixed>|null,
     *   nutrition: array<string, mixed>|null,
     *   nutrition_plan: array<string, mixed>|null,
     *   knowledge_context: array<string, mixed>|null
     * }
     */
    public function sendMessage(User $user, CoachConversation $conversation, string $message, ?string $language = null): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('Message is required.');
        }

        $userMessage = CoachMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => CoachMessage::ROLE_USER,
            'content' => $message,
        ]);

        if ($conversation->title === null) {
            $conversation->title = Str::limit($message, 60, '...');
            $conversation->save();
        }

        $history = CoachMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', '<', $userMessage->id)
            ->orderByDesc('id')
            ->limit(self::CONTEXT_LIMIT)
            ->get()
            ->sortBy('id')
            ->values()
            ->map(fn (CoachMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->all();

        $coachResult = $this->callCoachService($user, $message, $history, $language, $conversation->id);

        $assistantMessage = CoachMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => CoachMessage::ROLE_ASSISTANT,
            'content' => $coachResult['message'],
        ]);

        $conversation->touch();

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'conversation' => $conversation->fresh(),
            'grounded_exercises' => $coachResult['grounded_exercises'],
            'training_plan' => $coachResult['training_plan'],
            'nutrition' => $coachResult['nutrition'],
            'nutrition_plan' => $coachResult['nutrition_plan'],
            'knowledge_context' => $coachResult['knowledge_context'],
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{
     *   message: string,
     *   grounded_exercises: list<array<string, mixed>>,
     *   training_plan: array<string, mixed>|null,
     *   nutrition: array<string, mixed>|null,
     *   nutrition_plan: array<string, mixed>|null,
     *   knowledge_context: array<string, mixed>|null
     * }
     */
    private function callCoachService(
        User $user,
        string $message,
        array $history,
        ?string $language,
        ?int $conversationId = null,
    ): array {
        $baseUrl = rtrim((string) config('services.ai.coach_url'), '/');
        $token = (string) config('services.ai.internal_token');

        $user->loadMissing('profile');
        $locale = $user->profile?->locale;
        $resolvedLanguage = $language ?? (in_array($locale, ['en', 'ar'], true) ? $locale : null);
        $lang = in_array($resolvedLanguage, ['en', 'ar'], true) ? $resolvedLanguage : 'en';

        // Graduation demo layer — isolated; only when COACH_DEMO_MODE=true.
        // Unmatched messages fall through to the normal AI Coach unchanged.
        $demoLang = preg_match('/\p{Arabic}/u', $message) ? 'ar' : $lang;
        $demoResult = $this->demoScenarioService->tryHandle($user, $message, $conversationId, $demoLang);
        if (is_array($demoResult)) {
            return $demoResult;
        }

        // Phase 11H — extract stable preferences from the user message (server-side only).
        $this->memoryExtractor->processUserMessage($user, $message);

        $fullUserContext = $this->aiContextBuilder->forCoach($user, $message);
        $activeForRouting = $conversationId !== null ? $this->planContextStore->recall($conversationId) : null;
        $understood = $this->requestUnderstanding->understandFull(
            $message,
            $fullUserContext,
            $activeForRouting,
            $conversationId,
        );
        $routing = $understood['routing'];
        $followUpFromSemantic = $understood['follow_up'];
        $resolvedTrainingIntent = $understood['training_intent'] ?? [];
        $topicDecision = $understood['topic_decision'] ?? [];
        $contextRelevance = $understood['context_relevance'] ?? [];
        $responsePolicy = $understood['response_policy'] ?? $this->responsePolicy->forIntent(
            $understood['semantic'],
            $topicDecision,
        );
        $activeForEngines = $understood['active_plan_for_engines'] ?? null;
        // Prefer message language for Arabic food/sports Qs when client language omitted.
        if (($routing['language'] ?? null) === 'ar' && $language === null) {
            $lang = 'ar';
            $resolvedLanguage = 'ar';
        }

        Log::info('coach.request_routed', [
            'intent' => $routing['intent'] ?? null,
            'semantic_intent' => $routing['semantic_intent'] ?? null,
            'domain' => $routing['domain'] ?? null,
            'response_mode' => $routing['response_mode'] ?? null,
            'skip_profile' => $routing['skip_profile'] ?? false,
            'discipline' => $routing['discipline'] ?? null,
            'equipment_mode' => $routing['equipment_mode'] ?? null,
            'confidence' => $routing['confidence'] ?? null,
            'capabilities' => $routing['capabilities'] ?? [],
            'is_topic_switch' => $topicDecision['is_topic_switch'] ?? false,
            'is_follow_up' => $topicDecision['is_follow_up'] ?? false,
            'previous_topic' => $topicDecision['previous_topic'] ?? null,
            'current_topic' => $topicDecision['current_topic'] ?? null,
            'use_active_plan' => $contextRelevance['use_active_plan'] ?? false,
        ]);

        // Ambiguous request → one concise clarification (no engines).
        if (($routing['is_ambiguous'] ?? false) || ($routing['clarification_required'] ?? false)
            || (($responsePolicy['prefer_clarification'] ?? false) && ($routing['semantic_intent'] ?? '') === 'ambiguous_request')
        ) {
            $clarify = $this->responsePolicy->clarificationMessage($lang, 'program_type');

            return [
                'message' => $clarify,
                'grounded_exercises' => [],
                'training_plan' => null,
                'nutrition' => null,
                'nutrition_plan' => null,
                'knowledge_context' => [
                    'schema_version' => KnowledgeRetrievalService::SCHEMA_VERSION,
                    'query' => $message,
                    'topics' => [],
                    'item_count' => 0,
                    'items' => [],
                ],
            ];
        }

        // Prompt-injection / unsupported unsafe asks — refuse without leaking internals.
        if (in_array($routing['semantic_intent'] ?? '', ['unsupported_request', 'prompt_injection'], true)
            || (($understood['semantic']->subIntent ?? null) === 'prompt_injection')
        ) {
            $refuse = $lang === 'ar'
                ? 'ما بقدر أساعد بهالطلب. اسألني عن تمرين، تغذية، أو خطة تدريب ضمن OCTA FIT.'
                : "I can't help with that request. Ask me about training, nutrition, or an OCTA FIT plan.";

            return [
                'message' => $refuse,
                'grounded_exercises' => [],
                'training_plan' => null,
                'nutrition' => null,
                'nutrition_plan' => null,
                'knowledge_context' => [
                    'schema_version' => KnowledgeRetrievalService::SCHEMA_VERSION,
                    'query' => $message,
                    'topics' => [],
                    'item_count' => 0,
                    'items' => [],
                ],
            ];
        }

        // Context selection: semantic layer picks relevant slices.
        $userContext = ($routing['skip_profile'] ?? false)
            ? $this->slimContext($understood['user_context'])
            : $understood['user_context'];

        // Phase 11D — deterministic nutrition lookup (authoritative numbers from foods DB).
        $nutrition = null;
        $nutritionFallthrough = false;
        $wantNutritionLookup = ($routing['is_food_lookup'] ?? false)
            || (($routing['domain'] ?? '') === 'nutrition' && ! ($routing['is_nutrition_plan'] ?? false));
        if ($wantNutritionLookup) {
            $nutrition = $this->nutritionLookup->handleMessage($message, $lang);
        } elseif (! ($routing['is_plan_request'] ?? false)
            && ! ($routing['is_nutrition_plan'] ?? false)
            && ! ($routing['is_exercise_recommendation'] ?? false)
            && ! ($routing['is_general_sports'] ?? false)
            && ! ($topicDecision['is_topic_switch'] ?? false)
        ) {
            // Still allow lookup if extractor clearly matches (non-plan), but never after topic switch into training.
            $maybe = $this->nutritionLookup->handleMessage($message, $lang);
            if (is_array($maybe) && ($maybe['ok'] ?? false)) {
                $nutrition = $maybe;
            }
        }

        if (is_array($nutrition)
            && ! ($nutrition['ok'] ?? false)
            && ($nutrition['fallthrough_general_knowledge'] ?? false) === true
        ) {
            Log::info('coach.nutrition.fallthrough_general_knowledge', [
                'status' => $nutrition['status'] ?? null,
                'error_reasons' => array_column($nutrition['errors'] ?? [], 'reason'),
            ]);
            $nutritionFallthrough = true;
            $nutrition = null;
        }

        // Phase 11E — nutrition plans ONLY when semantic intent says so (never opportunistic).
        $nutritionPlan = null;
        $nutritionPlanResult = ['plan' => null, 'errors' => []];
        $wantNutritionPlan = ($routing['is_nutrition_plan'] ?? false)
            || in_array(($routing['semantic_intent'] ?? ''), ['nutrition_plan_create', 'nutrition_plan_modify'], true)
            || (in_array('nutrition_plan_create', $routing['secondary_intents'] ?? [], true))
            || (in_array('nutrition_plan_modify', $routing['secondary_intents'] ?? [], true));
        if ($wantNutritionPlan
            && $nutrition === null
            && ! ($routing['is_food_lookup'] ?? false)
            && ! $nutritionFallthrough
        ) {
            // Multi-intent may also need a training plan — do not require !is_plan_request.
            $nutritionPlanResult = $this->nutritionPlanEngine->generateFromMessage($user, $message, $fullUserContext, $lang);
            $nutritionPlan = $nutritionPlanResult['plan'];
            if (($nutritionPlanResult['preference_hints']['changed'] ?? false) || $nutritionPlan !== null) {
                $fullUserContext = $this->aiContextBuilder->forCoach($user, $message);
                $userContext = ($routing['skip_profile'] ?? false)
                    ? $this->slimContext($fullUserContext)
                    : $fullUserContext;
            }
        }

        // Phase 11C + intelligence — training plans / conversational follow-ups.
        // Active plan used ONLY when ContextRelevanceResolver allows it.
        $trainingPlan = null;
        $planResult = ['plan' => null, 'intent' => [], 'errors' => []];
        $active = $activeForEngines;
        $followUp = ($active !== null && ! ($routing['is_food_lookup'] ?? false) && $nutrition === null
            && ! ($topicDecision['is_topic_switch'] ?? false))
            ? $followUpFromSemantic
            : ['is_follow_up' => false];
        // Equipment exclusion still uses dedicated interpreter branch when semantic missed it.
        if (! ($followUp['is_follow_up'] ?? false) && $active !== null && $nutrition === null
            && ! ($topicDecision['is_topic_switch'] ?? false)
        ) {
            $equipFollow = $this->followUpInterpreter->interpret($message, $active);
            if (($equipFollow['action'] ?? '') === 'exclude_equipment') {
                $followUp = $equipFollow;
            }
        }

        $blockTraining = ($routing['is_food_lookup'] ?? false)
            || $nutritionFallthrough
            || (($routing['is_general_sports'] ?? false) && ! ($followUp['is_follow_up'] ?? false))
            || $nutrition !== null
            || (($nutritionPlan !== null || ($routing['is_nutrition_plan'] ?? false))
                && ! ($routing['is_multi_intent'] ?? false)
                && ! ($routing['is_plan_request'] ?? false));

        if (($followUp['is_follow_up'] ?? false) === true && $active !== null) {
            $blockTraining = false;
        }
        // Multi-intent: allow training + nutrition in the same turn.
        if (($routing['is_multi_intent'] ?? false) && ($routing['is_plan_request'] ?? false)) {
            $blockTraining = false;
        }
        // Topic switch into training must never be blocked by stale nutrition state.
        if (($topicDecision['is_topic_switch'] ?? false) === true
            && in_array($routing['semantic_intent'] ?? '', [
                'training_plan_create',
                'training_exercise_recommendation',
            ], true)
            && ! ($routing['is_multi_intent'] ?? false)
        ) {
            $blockTraining = false;
            $nutritionPlan = null;
        }

        if (! $blockTraining) {
            if (($followUp['is_follow_up'] ?? false) === true && $active !== null) {
                $action = (string) ($followUp['action'] ?? '');
                if ($action === 'resume_plan') {
                    $trainingPlan = $active['plan'];
                    $planResult = [
                        'plan' => $trainingPlan,
                        'intent' => $followUp['merged_intent'] ?? ($active['intent'] ?? []),
                        'errors' => [],
                    ];
                    Log::info('coach.intent_extracted', [
                        'intent' => 'follow_up_resume_plan',
                        'discipline' => $trainingPlan['discipline'] ?? null,
                    ]);
                } elseif ($action === 'intensify_day' && ($followUp['day'] ?? null) !== null) {
                    $before = $active['plan'];
                    $trainingPlan = $this->trainingPlanEngine->intensifyDay(
                        $before,
                        (int) $followUp['day'],
                    );
                    $planResult = [
                        'plan' => $trainingPlan,
                        'intent' => $followUp['merged_intent'] ?? ($active['intent'] ?? []),
                        'errors' => [],
                    ];
                    Log::info('coach.intent_extracted', [
                        'intent' => 'follow_up_intensify_day',
                        'day' => (int) $followUp['day'],
                        'discipline' => $planResult['intent']['discipline'] ?? $trainingPlan['discipline'] ?? null,
                    ]);
                } elseif ($action === 'replace_last_exercise') {
                    $replaced = $this->trainingPlanEngine->replaceLastExerciseOnDay(
                        $active['plan'],
                        isset($followUp['day']) ? (int) $followUp['day'] : null,
                        $lang,
                    );
                    if ($replaced['plan'] !== null) {
                        $trainingPlan = $replaced['plan'];
                        $planResult = [
                            'plan' => $trainingPlan,
                            'intent' => $followUp['merged_intent'] ?? ($active['intent'] ?? []),
                            'errors' => [],
                        ];
                        Log::info('coach.intent_extracted', [
                            'intent' => 'follow_up_replace_last_exercise',
                            'day' => $followUp['day'] ?? null,
                            'discipline' => $trainingPlan['discipline'] ?? null,
                        ]);
                    } else {
                        // Clarification / soft failure — do not invent a new plan.
                        $planResult = ['plan' => null, 'intent' => $active['intent'] ?? [], 'errors' => $replaced['errors']];
                        Log::info('coach.follow_up.replace_failed', ['errors' => $replaced['errors']]);
                    }
                } else {
                    $mergedIntent = $followUp['merged_intent'] ?? ($active['intent'] ?? []);
                    $mergedIntent['is_plan_request'] = true;
                    Log::info('coach.intent_extracted', [
                        'intent' => 'follow_up_'.$action,
                        'discipline' => $mergedIntent['discipline'] ?? null,
                        'days' => $mergedIntent['days_per_week'] ?? null,
                        'duration_minutes' => $mergedIntent['session_duration_minutes'] ?? null,
                    ]);
                    $planResult = $this->trainingPlanEngine->generate($mergedIntent, $fullUserContext, $lang);
                    $trainingPlan = $planResult['plan'];
                }
            } elseif ($routing['is_plan_request'] ?? false) {
                // CRITICAL: use semantically resolved intent — do NOT re-gate via generateFromMessage
                // keyword isPlanRequest (that caused silent fallthrough to generic Coach intro).
                $resolvedIntent = $resolvedTrainingIntent;
                $resolvedIntent['is_plan_request'] = true;
                if (($routing['discipline'] ?? null) !== null) {
                    $resolvedIntent['discipline'] = $routing['discipline'];
                    $resolvedIntent['discipline_explicit'] = true;
                }
                if (($routing['equipment_mode'] ?? null) !== null) {
                    $resolvedIntent['equipment_mode'] = $routing['equipment_mode'];
                }
                if (($routing['days'] ?? null) !== null) {
                    $resolvedIntent['days_per_week'] = (int) $routing['days'];
                    $resolvedIntent['days_explicit'] = true;
                }
                if (($routing['duration_minutes'] ?? null) !== null) {
                    $resolvedIntent['session_duration_minutes'] = (int) $routing['duration_minutes'];
                    $resolvedIntent['duration_explicit'] = true;
                }
                if (($routing['goal'] ?? null) !== null) {
                    $resolvedIntent['goal'] = $routing['goal'];
                }

                Log::info('coach.intent_extracted', [
                    'intent' => $routing['intent'] ?? 'training_plan',
                    'semantic_intent' => $routing['semantic_intent'] ?? null,
                    'discipline' => $resolvedIntent['discipline'] ?? null,
                    'days' => $resolvedIntent['days_per_week'] ?? null,
                    'duration_minutes' => $resolvedIntent['session_duration_minutes'] ?? null,
                    'equipment_mode' => $resolvedIntent['equipment_mode'] ?? null,
                    'discipline_explicit' => $resolvedIntent['discipline_explicit'] ?? false,
                    'confidence' => $routing['confidence'] ?? null,
                ]);

                $planResult = $this->trainingPlanEngine->generate($resolvedIntent, $fullUserContext, $lang);
                $trainingPlan = $planResult['plan'];

                Log::info('coach.execution', [
                    'capability' => 'training_plan',
                    'TrainingPlanEngine_called' => true,
                    'has_plan' => $trainingPlan !== null,
                    'errors' => $planResult['errors'] ?? [],
                    'discipline' => $trainingPlan['discipline'] ?? null,
                    'days' => $trainingPlan['days_per_week'] ?? null,
                    'duration_minutes' => $trainingPlan['requested_session_duration_minutes'] ?? null,
                ]);

                // Empty catalog / engine miss: fall through to FastAPI with full user_context
                // (do not hard-return — profile personalization must still reach the coach service).
                if ($trainingPlan === null) {
                    Log::warning('coach.validation', [
                        'result' => 'plan_engine_empty_fallthrough',
                        'semantic_intent' => $routing['semantic_intent'] ?? null,
                    ]);
                }
            }

            if ($trainingPlan !== null && $conversationId !== null) {
                $this->planContextStore->remember(
                    $conversationId,
                    $trainingPlan,
                    $planResult['intent'] ?? [],
                );
            }
        }

        // Soft clarification when replace failed with ambiguity.
        if ($trainingPlan === null
            && ($followUp['is_follow_up'] ?? false)
            && ($followUp['action'] ?? '') === 'replace_last_exercise'
            && in_array('day_ambiguous', $planResult['errors'] ?? [], true)
        ) {
            $clarify = $lang === 'ar'
                ? 'أي يوم تقصد؟ مثال: بدل آخر تمرين باليوم الثاني.'
                : 'Which day should I update? Example: replace the last exercise on day 2.';

            return [
                'message' => $clarify,
                'grounded_exercises' => [],
                'training_plan' => null,
                'nutrition' => null,
                'nutrition_plan' => null,
                'knowledge_context' => [
                    'schema_version' => KnowledgeRetrievalService::SCHEMA_VERSION,
                    'query' => $message,
                    'topics' => [],
                    'item_count' => 0,
                    'items' => [],
                ],
            ];
        }

        Log::info('coach.engine_selected', [
            'nutrition' => $nutrition !== null,
            'nutrition_fallthrough' => $nutritionFallthrough,
            'nutrition_plan' => $nutritionPlan !== null,
            'training_plan' => $trainingPlan !== null,
            'general_sports' => $routing['is_general_sports'] ?? false,
            'exercise_recommendation' => $routing['is_exercise_recommendation'] ?? false,
            'topic_switch' => $topicDecision['is_topic_switch'] ?? false,
        ]);

        $validation = $this->answerValidator->validate(
            $understood['semantic'],
            $routing,
            $topicDecision,
            $nutrition,
            $nutritionPlan,
            $trainingPlan,
        );
        Log::info('coach.answer_validation', [
            'ok' => $validation['ok'],
            'errors' => $validation['errors'],
            'retry' => $validation['retry_capability'],
        ]);
        if (! $validation['ok']) {
            // Correct routing bleed: drop wrong-domain engine outputs.
            if (in_array('topic_switch_nutrition_bleed', $validation['errors'], true)
                || in_array('training_plan_request_answered_with_nutrition_plan', $validation['errors'], true)
            ) {
                $nutritionPlan = null;
            }
            if (in_array('topic_switch_training_bleed', $validation['errors'], true)
                || in_array('nutrition_lookup_answered_with_training_plan', $validation['errors'], true)
                || in_array('exercise_request_answered_with_nutrition', $validation['errors'], true)
            ) {
                $trainingPlan = null;
                $nutritionPlan = null;
            }
            if (in_array('knowledge_answered_with_plan', $validation['errors'], true)
                || in_array('body_part_request_answered_with_full_plan', $validation['errors'], true)
            ) {
                $trainingPlan = null;
                $nutritionPlan = null;
            }
        }

        $candidates = [];
        if (($routing['needs_grounded_exercises'] ?? false) || $trainingPlan !== null) {
            // Scope control: pass NLU body_part/level so retrieval stays on the requested muscle.
            $retrievalContext = $fullUserContext;
            if (! empty($routing['body_part'])) {
                $retrievalContext['requested_body_part'] = (string) $routing['body_part'];
                $retrievalContext['body_part'] = (string) $routing['body_part'];
            }
            $semanticLevel = $understood['semantic']->entities['level']
                ?? $understood['semantic']->constraints['level']
                ?? null;
            if (is_string($semanticLevel) && $semanticLevel !== '') {
                $retrievalContext['requested_level'] = $semanticLevel;
            }
            $candidates = $this->exerciseRetrieval->retrieveForMessage($message, $retrievalContext, $lang);
            if ($trainingPlan !== null) {
                $planIds = [];
                foreach ($trainingPlan['sessions'] ?? [] as $session) {
                    foreach ($session['exercises'] ?? [] as $ex) {
                        if (isset($ex['exercise_id'])) {
                            $planIds[] = (int) $ex['exercise_id'];
                        }
                    }
                }
                $fromPlan = $this->exerciseRetrieval->validateIds($planIds, $lang)['valid'];
                if ($fromPlan !== []) {
                    $candidates = $fromPlan;
                }
            }
        }

        // Phase 11F — knowledge retrieval (skip for successful direct food answers).
        $knowledgeContext = null;
        $wantKnowledge = $nutrition === null
            && (! ($routing['is_food_lookup'] ?? false) || $nutritionFallthrough);
        if ($wantKnowledge) {
            $knowledgeContext = $this->buildKnowledgeContext(
                $message,
                $fullUserContext,
                $trainingPlan,
                $nutritionPlan,
            );
        }
        if ($knowledgeContext === null) {
            $knowledgeContext = [
                'schema_version' => KnowledgeRetrievalService::SCHEMA_VERSION,
                'query' => $message,
                'topics' => [],
                'item_count' => 0,
                'items' => [],
            ];
        }

        // Direct nutrition short-circuit — skip FastAPI (avoids profile-tainted answers).
        if (is_array($nutrition) && ($nutrition['ok'] ?? false)) {
            $direct = $this->nutritionLookup->formatMessage(
                $nutrition,
                $lang,
                (string) ($routing['response_mode'] ?? 'direct'),
            );
            Log::info('coach.response_validated', [
                'path' => 'nutrition_direct',
                'intent' => $routing['intent'] ?? 'food_lookup',
                'ok' => true,
            ]);

            return [
                'message' => $direct,
                'grounded_exercises' => [],
                'training_plan' => null,
                'nutrition' => $nutrition,
                'nutrition_plan' => null,
                'knowledge_context' => $knowledgeContext,
            ];
        }

        $payload = [
            'message' => $message,
            'conversation' => $history,
            'user_context' => $userContext,
            'exercise_candidates' => $candidates,
            'training_plan' => $trainingPlan,
            'nutrition' => $nutrition,
            'nutrition_plan' => $nutritionPlan,
            'knowledge_context' => $knowledgeContext,
            'routing' => [
                'intent' => $routing['intent'] ?? null,
                'domain' => $routing['domain'] ?? null,
                'response_mode' => $routing['response_mode'] ?? 'concise',
                'needs_personalization' => $routing['needs_personalization'] ?? false,
                'skip_profile' => $routing['skip_profile'] ?? false,
                'nutrition_fallthrough' => $nutritionFallthrough,
                'is_food_lookup' => $routing['is_food_lookup'] ?? false,
                'is_general_sports' => $routing['is_general_sports'] ?? false,
            ],
        ];
        if ($resolvedLanguage !== null && in_array($resolvedLanguage, ['en', 'ar'], true)) {
            $payload['language'] = $resolvedLanguage;
        }

        $started = microtime(true);
        $request = Http::timeout(45)
            ->acceptJson()
            ->asJson();

        if ($token !== '') {
            $request = $request->withHeaders(['X-Internal-Token' => $token]);
        }

        try {
            $response = $request->post($baseUrl.'/chat', $payload);
        } catch (ConnectionException $e) {
            Log::warning('coach.upstream_connection_failed', [
                'user_id' => $user->id,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'error_class' => class_basename($e),
            ]);
            throw new RuntimeException('Unable to reach the AI Coach service.');
        }

        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        if (! $response->successful()) {
            Log::warning('coach.upstream_http_failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'latency_ms' => $latencyMs,
            ]);
            throw new RuntimeException('Unable to reach the AI Coach service.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            Log::warning('coach.upstream_invalid_json', [
                'user_id' => $user->id,
                'latency_ms' => $latencyMs,
            ]);
            throw new RuntimeException('Unable to reach the AI Coach service.');
        }

        $content = $body['message'] ?? null;
        if (! is_string($content) || trim($content) === '') {
            Log::warning('coach.upstream_empty_message', [
                'user_id' => $user->id,
                'latency_ms' => $latencyMs,
                'provider' => is_string($body['provider'] ?? null) ? $body['provider'] : null,
            ]);
            throw new RuntimeException('Unable to reach the AI Coach service.');
        }

        Log::info('coach.upstream_ok', [
            'user_id' => $user->id,
            'latency_ms' => $latencyMs,
            'provider' => is_string($body['provider'] ?? null) ? $body['provider'] : null,
            'has_training_plan' => $trainingPlan !== null,
            'has_nutrition_plan' => $nutritionPlan !== null,
            'has_nutrition_lookup' => $nutrition !== null,
            'knowledge_items' => (int) ($knowledgeContext['item_count'] ?? 0),
            'memory_count' => (int) ($userContext['memory_context']['count'] ?? 0),
        ]);

        if ($nutrition !== null) {
            $content = $this->nutritionLookup->formatMessage(
                $nutrition,
                $lang,
                (string) ($routing['response_mode'] ?? 'direct'),
            );
        } elseif (($routing['is_multi_intent'] ?? false) && $trainingPlan !== null && $nutritionPlan !== null) {
            $trainMsg = $this->formatPlanMessage($trainingPlan, $lang, '');
            $nutMsg = $this->nutritionPlanEngine->formatMessage($nutritionPlan, $lang);
            $sep = $lang === 'ar' ? "\n\n——\n\n" : "\n\n——\n\n";
            $content = trim($trainMsg).$sep.trim($nutMsg);
        } elseif ($nutritionPlan !== null) {
            $content = $this->nutritionPlanEngine->formatMessage($nutritionPlan, $lang);
        } elseif ($trainingPlan !== null) {
            $content = $this->formatPlanMessage($trainingPlan, $lang, trim($content));
        } elseif (($routing['is_general_sports'] ?? false)
            && in_array(($routing['response_mode'] ?? ''), ['direct', 'concise'], true)) {
            // Keep prose natural — evidence stays in knowledge_context for Flutter UI.
            $content = $this->naturalGeneralSportsAnswer($message, $lang, trim($content));
        } elseif (($knowledgeContext['item_count'] ?? 0) > 0 && $this->knowledgeIntent->shouldRetrieve($message)) {
            // Detailed / personalized evidence questions may still get a source footer.
            $content = $this->appendKnowledgeFooter(trim($content), $knowledgeContext, $lang);
        }

        // Authoritative calorie/macro engines always surface when present (even on general-sports RAG).
        if (is_array($knowledgeContext['personalized_targets'] ?? null)
            && ($knowledgeContext['personalized_targets']['authoritative'] ?? false)
            && ! ($routing['is_food_lookup'] ?? false)
            && $trainingPlan === null
            && $nutrition === null
            && $nutritionPlan === null
        ) {
            $content = $this->appendKnowledgeFooter(trim($content), [
                'personalized_targets' => $knowledgeContext['personalized_targets'],
                'items' => [],
            ], $lang);
        }

        Log::info('coach.response_validated', [
            'intent' => $routing['intent'] ?? null,
            'has_training_plan' => $trainingPlan !== null,
            'has_nutrition' => $nutrition !== null,
            'skip_profile' => $routing['skip_profile'] ?? false,
        ]);

        $claimedIds = $this->extractClaimedExerciseIds(is_array($body) ? $body : []);
        $validated = $this->exerciseRetrieval->validateIds($claimedIds, $lang);

        if ($validated['rejected'] !== []) {
            Log::debug('coach.exercise_grounding.post_validate_rejected', [
                'rejected' => $validated['rejected'],
            ]);
        }

        $grounded = $validated['valid'] !== []
            ? $validated['valid']
            : $candidates;

        if (($planResult['errors'] ?? []) !== []) {
            Log::debug('coach.training_plan.generation_errors', ['errors' => $planResult['errors']]);
        }
        if (($nutritionPlanResult['errors'] ?? []) !== []) {
            Log::debug('coach.nutrition_plan.generation_errors', ['errors' => $nutritionPlanResult['errors']]);
        }

        $skipGrounded = $nutrition !== null || $nutritionPlan !== null;

        return [
            'message' => trim($content),
            'grounded_exercises' => $skipGrounded ? [] : $grounded,
            'training_plan' => $trainingPlan,
            'nutrition' => $nutrition,
            'nutrition_plan' => $nutritionPlan,
            'knowledge_context' => $knowledgeContext,
        ];
    }

    /**
     * @param  array<string, mixed>  $userContext
     * @param  array<string, mixed>|null  $trainingPlan
     * @param  array<string, mixed>|null  $nutritionPlan
     * @return array<string, mixed>
     */
    private function buildKnowledgeContext(
        string $message,
        array $userContext,
        ?array $trainingPlan,
        ?array $nutritionPlan,
    ): array {
        $personalizedTargets = $this->personalizedTargetsIfAsked($message, $userContext);
        $wantEvidence = $this->knowledgeIntent->shouldRetrieve($message)
            || $trainingPlan !== null
            || $nutritionPlan !== null
            || $personalizedTargets !== null;

        if (! $wantEvidence) {
            return [
                'schema_version' => KnowledgeRetrievalService::SCHEMA_VERSION,
                'query' => $message,
                'topics' => [],
                'item_count' => 0,
                'items' => [],
                'limitations' => ['retrieval_not_needed'],
                'retrieval_backend' => 'lexical_topic_v1',
                'personalized_targets' => $personalizedTargets,
            ];
        }

        $ctx = $this->knowledgeRetrieval->retrieveForMessage($message, $userContext, 5);

        // Supporting evidence for generated plans when query itself wasn't "evidence-like".
        if (($ctx['item_count'] ?? 0) === 0 && ($trainingPlan !== null || $nutritionPlan !== null)) {
            $topics = $trainingPlan !== null ? ['training'] : ['nutrition'];
            $goal = (string) (($trainingPlan['goal'] ?? null) ?? ($nutritionPlan['goal'] ?? 'hypertrophy'));
            $ctx = $this->knowledgeRetrieval->retrieve(
                $message.' '.$goal,
                $topics,
                [$goal, 'progressive', 'protein', 'hypertrophy'],
                $userContext,
                3,
            );
        }

        $ctx['personalized_targets'] = $personalizedTargets;

        return $ctx;
    }

    /**
     * Deterministic protein/calorie targets for personalization questions (authoritative numbers).
     *
     * @param  array<string, mixed>  $userContext
     * @return array<string, mixed>|null
     */
    private function personalizedTargetsIfAsked(string $message, array $userContext): ?array
    {
        $lower = mb_strtolower($message);
        $asksProtein = (bool) preg_match('/\b(how much protein|protein should i|بروتين)\b/u', $lower);
        $asksCalories = (bool) preg_match('/\b(how many calories|calorie target|سعرات)\b/u', $lower)
            && ! preg_match('/\b(in \d|g of|grams of)\b/u', $lower);
        if (! $asksProtein && ! $asksCalories) {
            return null;
        }

        $goal = 'maintenance';
        $g = mb_strtolower((string) ($userContext['goals']['primary_goal'] ?? $userContext['primary_goal'] ?? ''));
        if (str_contains($g, 'muscle') || str_contains($g, 'bulk') || str_contains($lower, 'bulk')) {
            $goal = 'muscle_gain';
        } elseif (str_contains($g, 'fat') || str_contains($g, 'cut') || str_contains($lower, 'cut')) {
            $goal = 'fat_loss';
        }

        $cal = $this->calorieTargets->calculate($userContext, $goal);
        if (! ($cal['ok'] ?? false)) {
            return null;
        }
        $macros = $this->macroTargets->calculate((int) $cal['calorie_target'], $goal, $userContext);

        return [
            'authoritative' => true,
            'engine' => 'phase_11e_calorie_macro',
            'goal' => $goal,
            'calorie_target' => $cal['calorie_target'],
            'macro_targets' => [
                'protein_g' => $macros['protein_g'] ?? null,
                'carbohydrates_g' => $macros['carbohydrates_g'] ?? null,
                'fat_g' => $macros['fat_g'] ?? null,
            ],
            'assumptions' => array_values(array_unique(array_merge(
                $cal['assumptions'] ?? [],
                $macros['assumptions'] ?? [],
            ))),
            'note' => 'Numbers come from deterministic engines, not RAG or the LLM.',
        ];
    }

    /**
     * @param  array<string, mixed>  $knowledge
     */
    private function appendKnowledgeFooter(string $content, array $knowledge, string $lang): string
    {
        $targets = $knowledge['personalized_targets'] ?? null;
        if (is_array($targets) && ($targets['authoritative'] ?? false)) {
            $prefix = $lang === 'ar'
                ? 'حساب حتمي (ليس من نموذج اللغة): '
                : 'Deterministic estimate (not from the LLM): ';
            $prefix .= ($targets['calorie_target'] ?? '?').' kcal/day';
            if (isset($targets['macro_targets']['protein_g'])) {
                $prefix .= ', protein ~'.$targets['macro_targets']['protein_g'].' g/day';
            }
            $content = $prefix."\n\n".$content;
        }

        $items = $knowledge['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return $content;
        }

        $lines = [$content, '', $lang === 'ar' ? 'مصادر مسترجعة:' : 'Retrieved sources:'];
        foreach (array_slice($items, 0, 5) as $item) {
            $line = '• '.($item['source'] ?? $item['title'] ?? 'Source');
            if (! empty($item['organization'])) {
                $line .= ' — '.$item['organization'];
            }
            if (! empty($item['publication_year'])) {
                $line .= ' ('.$item['publication_year'].')';
            }
            if (! empty($item['evidence_level'])) {
                $line .= ' ['.$item['evidence_level'].']';
            }
            if (! empty($item['url'])) {
                $line .= ' '.$item['url'];
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function formatPlanMessage(array $plan, string $lang, string $providerMessage): string
    {
        $lines = [];
        $lines[] = $plan['title'] ?? ($lang === 'ar' ? 'خطة تدريب' : 'Training plan');
        $lines[] = '';
        $lines[] = ($lang === 'ar' ? 'الهدف: ' : 'Goal: ').($plan['goal'] ?? '');
        $lines[] = ($lang === 'ar' ? 'التخصص: ' : 'Discipline: ').($plan['discipline'] ?? '');
        $lines[] = ($lang === 'ar' ? 'الأيام/أسبوع: ' : 'Days/week: ').($plan['days_per_week'] ?? '');
        $lines[] = ($lang === 'ar' ? 'المستوى: ' : 'Level: ').($plan['level'] ?? '');
        $requestedDur = $plan['requested_session_duration_minutes']
            ?? $plan['estimated_session_duration_minutes']
            ?? null;
        if ($requestedDur !== null) {
            $lines[] = ($lang === 'ar' ? 'مدة الجلسة (دقيقة): ' : 'Session duration (min): ').$requestedDur;
        }
        $lines[] = '';

        foreach ($plan['sessions'] ?? [] as $session) {
            $lines[] = 'Day '.($session['day'] ?? '?').' — '.($session['title'] ?? '');
            foreach ($session['exercises'] ?? [] as $ex) {
                $rx = $ex['reps'] ?? (($ex['rounds'] ?? null) ? (($ex['rounds']).' rounds') : '');
                $lines[] = '• '.($ex['name'] ?? 'Exercise').' (#'.$ex['exercise_id'].') — '
                    .($ex['sets'] ?? '?').' × '.($rx ?: 'timed')
                    .' / rest '.($ex['rest_seconds'] ?? '?').'s';
            }
            $lines[] = '';
        }

        if (! empty($plan['progression_strategy'])) {
            $lines[] = ($lang === 'ar' ? 'التقدم: ' : 'Progression: ').$plan['progression_strategy'];
        }
        $lines[] = $lang === 'ar'
            ? 'ملاحظة: هذه خطة تدريب — التغذية الشخصية متاحة عبر طلب خطة غذائية.'
            : 'Note: Training plan — ask separately for a personalized nutrition plan.';

        if ($providerMessage !== '' && ! str_contains(mb_strtolower($providerMessage), 'octa fit library')) {
            $dumpMarkers = [
                'مصادر مسترجعة', 'Retrieved sources', 'ملخص قائم على مصادر',
                'Evidence-oriented summary', 'أنا مدرب أوكتا فيت', 'I am the OCTA FIT Sports Coach',
                'بناءً على ملفك', 'Based on your profile',
            ];
            $isDump = false;
            foreach ($dumpMarkers as $marker) {
                if (str_contains($providerMessage, $marker)) {
                    $isDump = true;
                    break;
                }
            }
            if (! $isDump) {
                $lines[] = '';
                $lines[] = $providerMessage;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<int>
     */
    private function extractClaimedExerciseIds(array $body): array
    {
        $ids = [];
        $items = $body['grounded_exercises'] ?? $body['exercises'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if (is_numeric($item)) {
                $ids[] = (int) $item;

                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $raw = $item['exercise_id'] ?? $item['id'] ?? null;
            if (is_numeric($raw)) {
                $ids[] = (int) $raw;
            }
        }

        return $ids;
    }

    /**
     * Natural concise sports answers — never dump RAG metadata into chat prose.
     */
    private function naturalGeneralSportsAnswer(string $message, string $lang, string $providerMessage): string
    {
        $lower = mb_strtolower($message);
        if (preg_match('/progressive\s*overload|تقدم\s*تدريجي/u', $lower)) {
            return $lang === 'ar'
                ? 'Progressive overload يعني إنك تزيد تحدّي التمرين تدريجيًا مع الوقت، مثل زيادة الوزن أو التكرارات أو المجموعات أو تحسين الأداء بنفس الوزن. مثال: إذا سويت 10 تكرارات بسهولة، زِد الوزن قليلًا في الجلسة الجاية.'
                : 'Progressive overload means gradually increasing training demand over time — more weight, more reps, more sets, or better control with the same load. Example: if 10 reps feel easy, add a little load next session.';
        }
        if (preg_match('/rest between|بين (المجموعات|الجولات)|راحة بين/u', $lower)) {
            return $lang === 'ar'
                ? 'الراحة بين المجموعات تعتمد على الهدف: قوة ≈ 2–5 دقائق، تضخيم ≈ 60–120 ثانية، وتحمل/كارديو أقصر عادةً. اختر الراحة التي تحافظ على جودة التنفيذ.'
                : 'Rest between sets depends on the goal: strength ≈ 2–5 minutes, hypertrophy ≈ 60–120 seconds, and conditioning usually shorter. Choose rest that keeps good form.';
        }

        // Prefer provider text only if it does not look like an internal retrieval dump.
        $dumpMarkers = ['مصادر مسترجعة', 'Retrieved sources', 'ملخص قائم على مصادر', 'Evidence-oriented summary', 'schema_version', 'retrieved knowledge'];
        foreach ($dumpMarkers as $marker) {
            if ($providerMessage !== '' && str_contains($providerMessage, $marker)) {
                return $lang === 'ar'
                    ? 'هذا مبدأ تدريبي عام. اسأل بشكل أوضح إذا تحب مثال عملي على خطتك.'
                    : 'That is a general training principle. Ask with more detail if you want a practical example for your plan.';
            }
        }

        if ($providerMessage !== ''
            && ! str_contains($providerMessage, 'بناءً على ملفك')
            && ! str_contains($providerMessage, 'Based on your profile')) {
            return $providerMessage;
        }

        return $lang === 'ar'
            ? 'أقدر أشرح مبادئ التدريب والتغذية الرياضية باختصار. حدّد سؤالك أكثر لأجاوب بدقة.'
            : 'I can briefly explain training and sports-nutrition principles. Make the question more specific for a sharper answer.';
    }

    /**
     * Minimal context for simple factual questions — no profile personalization dump.
     *
     * @param  array<string, mixed>  $full
     * @return array<string, mixed>
     */
    private function slimContext(array $full): array
    {
        return [
            'available' => false,
            'schema_version' => $full['schema_version'] ?? '11a.1',
            'identity' => [
                'locale' => $full['identity']['locale'] ?? $full['locale'] ?? 'en',
            ],
            'note' => 'Profile omitted — not relevant to this request.',
        ];
    }
}
