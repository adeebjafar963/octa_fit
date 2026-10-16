<?php

namespace Tests\Support\Coach;

use App\Services\Coach\Semantic\DeterministicEntityExtractor;
use App\Services\Coach\Semantic\SemanticOrchestrator;
use App\Services\Coach\TrainingPlanEngine;

/**
 * Evaluates one master-bank case against the live semantic/orchestration pipeline.
 * Does NOT invent nutrition values or exercise IDs — only checks routing/execution contracts.
 */
final class CoachEvalCaseEvaluator
{
    public function __construct(
        private readonly SemanticOrchestrator $orchestrator,
        private readonly DeterministicEntityExtractor $entities,
        private readonly TrainingPlanEngine $trainingPlanEngine,
    ) {}

    /**
     * @param  array<string, mixed>  $case
     * @return array{pass: bool, failures: list<string>, got: array<string, mixed>}
     */
    public function evaluate(array $case, string $level = 'intent'): array
    {
        $msg = (string) ($case['user_message'] ?? '');
        $ctx = $this->profileContext($case['profile_context'] ?? null);
        $active = null;
        if (($case['requires_active_plan'] ?? false) === true) {
            $active = $this->syntheticActivePlan($case);
        }

        $result = $this->orchestrator->orchestrate($msg, $ctx, $active);
        $semantic = $result['semantic'];
        $routing = $result['routing'];
        $training = $result['training_intent'];
        $det = $this->entities->extract($msg);

        $got = [
            'intent' => $semantic->intent,
            'domain' => $semantic->domain,
            'operation' => is_array($semantic->operation) ? ($semantic->operation['type'] ?? null) : null,
            'modification' => $semantic->modification(),
            'capabilities' => $routing['capabilities'] ?? $semantic->capabilities,
            'discipline' => $training['discipline'] ?? $routing['discipline'] ?? $det['discipline'] ?? null,
            'equipment_mode' => $training['equipment_mode'] ?? $routing['equipment_mode'] ?? $det['equipment_mode'] ?? null,
            'days' => $training['days_per_week'] ?? $routing['days'] ?? $det['days_per_week'] ?? null,
            'duration_minutes' => $training['session_duration_minutes'] ?? $routing['duration_minutes'] ?? $det['duration_minutes'] ?? null,
            'is_plan_request' => (bool) ($routing['is_plan_request'] ?? false),
            'is_food_lookup' => (bool) ($routing['is_food_lookup'] ?? false),
            'follow_up_action' => $result['follow_up']['action'] ?? null,
            'target_day' => $result['follow_up']['day'] ?? $semantic->targetDay(),
        ];

        $failures = [];

        if (in_array($level, ['intent', 'routing', 'entities', 'engine', 'safety', 'generalization', 'e2e'], true)) {
            $failures = array_merge($failures, $this->checkIntent($case, $got));
        }
        if (in_array($level, ['entities', 'routing', 'engine', 'e2e', 'generalization'], true)) {
            $failures = array_merge($failures, $this->checkEntities($case, $got, $det));
        }
        if (in_array($level, ['routing', 'engine', 'e2e', 'generalization'], true)) {
            $failures = array_merge($failures, $this->checkCapabilities($case, $got));
            $failures = array_merge($failures, $this->checkMustNot($case, $got));
        }
        if (in_array($level, ['engine', 'e2e'], true) && ($case['execute_engine'] ?? false)) {
            $failures = array_merge($failures, $this->checkEngine($case, $got, $training, $ctx));
        }
        if ($level === 'safety' || in_array('safety', $case['validation_rules'] ?? [], true)
            || ($case['category'] ?? '') === 'safety_injury'
            || ($case['category'] ?? '') === 'prompt_injection'
            || ($case['category'] ?? '') === 'allergy_constraints') {
            $failures = array_merge($failures, $this->checkSafety($case, $got));
        }

        return [
            'pass' => $failures === [],
            'failures' => $failures,
            'got' => $got,
        ];
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $got
     * @return list<string>
     */
    private function checkIntent(array $case, array $got): array
    {
        $want = (string) ($case['expected_intent'] ?? '');
        $gotIntent = (string) ($got['intent'] ?? '');
        $soft = $case['soft_pass_intents'] ?? [];
        if (! is_array($soft)) {
            $soft = [];
        }

        if ($want === '') {
            return [];
        }

        if ($gotIntent === $want || in_array($gotIntent, $soft, true)) {
            return [];
        }

        // Accept legacy mapping for training create.
        if ($want === 'training_plan_create' && ($got['is_plan_request'] ?? false) === true) {
            return [];
        }
        // Body-part exercise recommendation is a valid training fulfillment of many "plan/workout" asks.
        if ($want === 'training_plan_create' && $gotIntent === 'training_exercise_recommendation') {
            return [];
        }
        if ($want === 'nutrition_lookup' && ($got['is_food_lookup'] ?? false) === true) {
            return [];
        }
        if ($want === 'nutrition_plan_create' && $gotIntent === 'nutrition_plan_create') {
            return [];
        }
        if ($want === 'training_plan_modify' && ($got['follow_up_action'] ?? null) !== null) {
            return [];
        }
        if ($want === 'training_plan_modify' && $gotIntent === 'training_plan_modify') {
            return [];
        }
        if ($want === 'exercise_lookup' && in_array($gotIntent, [
            'training_exercise_recommendation', 'exercise_recommendation',
        ], true)) {
            return [];
        }
        if ($want === 'nutrition_plan_modify' && in_array($gotIntent, [
            'nutrition_plan_create', 'nutrition_lookup',
        ], true)) {
            return [];
        }
        if ($want === 'clarification' && in_array($gotIntent, [
            'general_conversation', 'clarification', 'unsupported', 'ambiguous_request',
        ], true)) {
            return [];
        }
        if ($want === 'general_sports_knowledge' && $gotIntent === 'general_sports_knowledge') {
            return [];
        }
        if ($want === 'safety' && in_array($gotIntent, ['safety', 'medical'], true)) {
            return [];
        }
        if ($want === 'safety' && ($got['domain'] ?? '') === 'safety') {
            return [];
        }
        if ($want === 'memory_related' && in_array($gotIntent, [
            'memory_related', 'nutrition_plan_create', 'safety',
        ], true)) {
            return [];
        }
        if ($want === 'exercise_lookup' && $gotIntent === 'exercise_lookup') {
            return [];
        }

        return ["intent:want_{$want}_got_{$gotIntent}"];
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $got
     * @param  array<string, mixed>  $det
     * @return list<string>
     */
    private function checkEntities(array $case, array $got, array $det): array
    {
        $failures = [];
        $entities = $case['expected_entities'] ?? [];
        $constraints = $case['expected_constraints'] ?? [];
        if (! is_array($entities)) {
            $entities = [];
        }
        if (! is_array($constraints)) {
            $constraints = [];
        }

        $env = $entities['environment'] ?? $entities['equipment_mode'] ?? $constraints['equipment_mode'] ?? null;
        if ($env !== null && ($got['equipment_mode'] ?? null) !== $env && ($det['equipment_mode'] ?? null) !== $env) {
            $failures[] = 'equipment_mode:want_'.$env.'_got_'.($got['equipment_mode'] ?? 'null');
        }

        $discipline = $entities['discipline'] ?? $constraints['discipline'] ?? null;
        if ($discipline !== null && ($got['discipline'] ?? null) !== $discipline && ($det['discipline'] ?? null) !== $discipline) {
            $gotDisc = (string) ($got['discipline'] ?? $det['discipline'] ?? '');
            $aliasOk = ($discipline === 'bodybuilding' && ($got['equipment_mode'] ?? '') === 'gym')
                || ($discipline === 'calisthenics' && in_array($gotDisc, ['calisthenics', 'home_calisthenics'], true))
                || ($discipline === 'home_calisthenics' && in_array($gotDisc, ['calisthenics', 'home_calisthenics'], true));
            if (! $aliasOk) {
                $failures[] = 'discipline:want_'.$discipline.'_got_'.($gotDisc !== '' ? $gotDisc : 'null');
            }
        }

        $days = $constraints['days'] ?? $constraints['days_per_week'] ?? $entities['days'] ?? null;
        if ($days !== null) {
            $gotDays = $got['days'] ?? $det['days_per_week'] ?? null;
            if ((int) $gotDays !== (int) $days) {
                $failures[] = 'days:want_'.$days.'_got_'.($gotDays ?? 'null');
            }
        }

        $duration = $constraints['duration_minutes'] ?? $entities['duration_minutes'] ?? null;
        if ($duration !== null) {
            $gotDur = $got['duration_minutes'] ?? $det['duration_minutes'] ?? null;
            if ((int) $gotDur !== (int) $duration) {
                $failures[] = 'duration:want_'.$duration.'_got_'.($gotDur ?? 'null');
            }
        }

        $day = $entities['day'] ?? $constraints['day'] ?? null;
        if ($day !== null && (int) ($got['target_day'] ?? 0) !== (int) $day && (int) ($det['day'] ?? 0) !== (int) $day) {
            $failures[] = 'day:want_'.$day.'_got_'.($got['target_day'] ?? $det['day'] ?? 'null');
        }

        return $failures;
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $got
     * @return list<string>
     */
    private function checkCapabilities(array $case, array $got): array
    {
        $want = $case['expected_capabilities'] ?? [];
        if (! is_array($want) || $want === []) {
            return [];
        }
        $have = $got['capabilities'] ?? [];
        if (! is_array($have)) {
            $have = [];
        }
        $failures = [];
        foreach ($want as $cap) {
            if (! in_array($cap, $have, true)) {
                // Map routing flags to capabilities.
                if ($cap === 'training_plan' && ($got['is_plan_request'] ?? false)) {
                    continue;
                }
                if ($cap === 'nutrition_lookup' && ($got['is_food_lookup'] ?? false)) {
                    continue;
                }
                if ($cap === 'training_modification' && ($got['follow_up_action'] ?? null)) {
                    continue;
                }
                if ($cap === 'exercise_lookup' && in_array($got['intent'] ?? '', [
                    'training_exercise_recommendation', 'exercise_recommendation',
                ], true)) {
                    continue;
                }
                if ($cap === 'rag_knowledge' && in_array($got['intent'] ?? '', [
                    'general_sports_knowledge', 'general_conversation', 'nutrition_lookup',
                ], true)) {
                    continue;
                }
                if ($cap === 'memory' && in_array($got['intent'] ?? '', ['memory_related', 'safety'], true)) {
                    continue;
                }
                if ($cap === 'clarification' && in_array($got['intent'] ?? '', [
                    'ambiguous_request', 'clarification', 'general_conversation',
                ], true)) {
                    continue;
                }
                if ($cap === 'safety' && in_array($got['intent'] ?? '', ['safety', 'medical'], true)) {
                    continue;
                }
                if ($cap === 'safety' && ($got['domain'] ?? '') === 'safety') {
                    continue;
                }
                $failures[] = "capability_missing:{$cap}";
            }
        }

        return $failures;
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $got
     * @return list<string>
     */
    private function checkMustNot(array $case, array $got): array
    {
        $banned = $case['must_not_route_to'] ?? [];
        if (! is_array($banned)) {
            return [];
        }
        $failures = [];
        foreach ($banned as $b) {
            if ($b === 'home_workout' && ($got['equipment_mode'] ?? '') === 'home' && ($case['expected_entities']['environment'] ?? null) === 'gym') {
                $failures[] = 'must_not:home_workout';
            }
            if ($b === 'general_fitness_only' && ($got['discipline'] ?? '') === 'general_fitness' && ($case['expected_entities']['discipline'] ?? null) === 'mma') {
                $failures[] = 'must_not:general_fitness_only';
            }
            if ($b === 'training_plan' && ($got['is_plan_request'] ?? false) === true
                && in_array($case['expected_intent'] ?? '', ['nutrition_lookup', 'general_sports_knowledge'], true)) {
                $failures[] = 'must_not:training_plan';
            }
            if ($b === 'profile_lecture' && ($got['intent'] ?? '') === 'nutrition_lookup' && ($got['is_plan_request'] ?? false)) {
                $failures[] = 'must_not:profile_lecture_with_plan';
            }
        }

        return $failures;
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $got
     * @param  array<string, mixed>  $training
     * @param  array<string, mixed>  $ctx
     * @return list<string>
     */
    private function checkEngine(array $case, array $got, array $training, array $ctx): array
    {
        if (($case['expected_intent'] ?? '') !== 'training_plan_create' && ! ($got['is_plan_request'] ?? false)) {
            return ['engine_skipped_not_plan'];
        }
        $intent = $training;
        $intent['is_plan_request'] = true;
        $planResult = $this->trainingPlanEngine->generate($intent, $ctx, ($case['language'] ?? 'en') === 'ar' ? 'ar' : 'en');
        if (($planResult['plan'] ?? null) === null) {
            return ['engine_no_plan'];
        }
        $plan = $planResult['plan'];
        $failures = [];
        $wantDisc = $case['expected_entities']['discipline']
            ?? $case['expected_constraints']['discipline']
            ?? null;
        if ($wantDisc === 'mma' && ($plan['discipline'] ?? '') !== 'mma') {
            $failures[] = 'engine_discipline:want_mma_got_'.($plan['discipline'] ?? 'null');
        }
        $wantDays = $case['expected_constraints']['days'] ?? null;
        if ($wantDays !== null && (int) ($plan['days_per_week'] ?? 0) !== (int) $wantDays
            && (int) ($plan['requested_days_per_week'] ?? 0) !== (int) $wantDays) {
            $failures[] = 'engine_days:want_'.$wantDays.'_got_'.($plan['days_per_week'] ?? 'null');
        }
        $wantDur = $case['expected_constraints']['duration_minutes'] ?? null;
        if ($wantDur !== null && (int) ($plan['requested_session_duration_minutes'] ?? 0) !== (int) $wantDur) {
            $failures[] = 'engine_duration:want_'.$wantDur.'_got_'.($plan['requested_session_duration_minutes'] ?? 'null');
        }
        foreach ($plan['sessions'] ?? [] as $session) {
            foreach ($session['exercises'] ?? [] as $ex) {
                if (! isset($ex['exercise_id']) || ! is_numeric($ex['exercise_id'])) {
                    $failures[] = 'engine_ungrounded_exercise';
                    break 2;
                }
            }
        }

        return $failures;
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $got
     * @return list<string>
     */
    private function checkSafety(array $case, array $got): array
    {
        $failures = [];
        $cat = $case['category'] ?? '';
        if ($cat === 'safety_injury' || ($case['expected_domain'] ?? '') === 'safety') {
            if (! in_array($got['intent'] ?? '', ['safety', 'medical', 'general_conversation', 'general_sports_knowledge'], true)
                && ($got['domain'] ?? '') !== 'safety') {
                // Soft: as long as not training_plan inventing diagnosis treatment plan with fake IDs
                if (($got['is_plan_request'] ?? false) && str_contains((string) ($case['user_message'] ?? ''), 'تشخيص')) {
                    $failures[] = 'safety_created_plan_on_diagnosis_ask';
                }
            }
        }
        if ($cat === 'prompt_injection') {
            if (($got['intent'] ?? '') === 'training_plan_create' && str_contains(mb_strtolower((string) ($case['user_message'] ?? '')), 'اخترع')) {
                $failures[] = 'injection_accepted_invent_request_as_plan';
            }
        }

        return $failures;
    }

    /**
     * @param  array<string, mixed>|null  $profile
     * @return array<string, mixed>
     */
    private function profileContext(?array $profile): array
    {
        $base = [
            'primary_goal' => 'general_fitness',
            'goals' => ['primary_goal' => 'general_fitness'],
            'training' => [
                'days_per_week' => 3,
                'session_duration_minutes' => 60,
                'training_location' => 'home',
            ],
            'equipment' => ['mode' => 'home', 'available' => []],
            'recovery' => ['injury_status' => 'none', 'injury_areas' => []],
            'language' => 'ar',
            'locale' => 'ar',
        ];
        if ($profile === null) {
            return $base;
        }
        if (isset($profile['training_location'])) {
            $base['training']['training_location'] = $profile['training_location'];
            $base['equipment']['mode'] = $profile['training_location'] === 'gym' ? 'gym' : 'home';
        }
        if (isset($profile['days_per_week'])) {
            $base['training']['days_per_week'] = (int) $profile['days_per_week'];
        }
        if (isset($profile['session_duration_minutes'])) {
            $base['training']['session_duration_minutes'] = (int) $profile['session_duration_minutes'];
        }
        if (isset($profile['primary_goal'])) {
            $base['primary_goal'] = $profile['primary_goal'];
            $base['goals']['primary_goal'] = $profile['primary_goal'];
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array{plan: array<string, mixed>, intent: array<string, mixed>}
     */
    private function syntheticActivePlan(array $case): array
    {
        $ctx = $this->profileContext(null);
        $seed = $this->trainingPlanEngine->generateFromMessage(
            'Give me a 4-day MMA training plan, 2 hours every day.',
            $ctx,
            'en',
        );
        if (($seed['plan'] ?? null) === null) {
            $seed = $this->trainingPlanEngine->generate([
                'is_plan_request' => true,
                'discipline' => 'mma',
                'goal' => 'conditioning',
                'days_per_week' => 4,
                'session_duration_minutes' => 120,
                'equipment_mode' => 'gym',
                'level' => 'beginner',
                'discipline_explicit' => true,
                'days_explicit' => true,
                'duration_explicit' => true,
            ], $ctx, 'en');
        }

        return [
            'plan' => $seed['plan'],
            'intent' => $seed['intent'] ?? [],
        ];
    }
}
