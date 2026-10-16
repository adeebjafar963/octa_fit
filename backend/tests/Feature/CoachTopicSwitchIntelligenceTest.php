<?php

namespace Tests\Feature;

use App\Services\Coach\Provider\OpenAISemanticProvider;
use App\Services\Coach\Response\CoachResponsePolicy;
use App\Services\Coach\Semantic\ConversationTopicState;
use App\Services\Coach\Semantic\SemanticOrchestrator;
use App\Services\Coach\Semantic\TopicSwitchDetector;
use App\Services\Coach\Validation\CoachAnswerValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoachTopicSwitchIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function transitionProvider(): array
    {
        $cases = [];
        // nutrition → training
        foreach ([
            ['كم سعرة في تفاحة؟', 'بدي تمرين صدر', 'nutrition_lookup', 'training_exercise_recommendation'],
            ['كم بروتين بالبيض؟', 'اعمللي برنامج جيم', 'nutrition_lookup', 'training_plan_create'],
            ['calories in apple', 'give me a chest workout', 'nutrition_lookup', 'training_exercise_recommendation'],
            ['how much protein in chicken', 'I want a 4 day gym program', 'nutrition_lookup', 'training_plan_create'],
            ['قديش سعرات الموز', 'بدي أكتاف', 'nutrition_lookup', 'training_exercise_recommendation'],
            ['شو فيه رز سعرات', 'بدي MMA', 'nutrition_lookup', 'training_plan_create'],
            ['كم سعرة بتفاحتين', 'بدي تمرين ظهر', 'nutrition_lookup', 'training_exercise_recommendation'],
            ['protein in eggs', 'home workout program', 'nutrition_lookup', 'training_plan_create'],
            ['سعرات الشوفان', 'بدي برنامج حديد', 'nutrition_lookup', 'training_plan_create'],
            ['كم بروتين في 200 غرام دجاج؟', 'بدي تمرين صدر بالبيت', 'nutrition_lookup', 'training_exercise_recommendation'],
        ] as $row) {
            $cases[] = $row;
        }

        // training → nutrition
        foreach ([
            // Body-part + برنامج is a focused muscle request (not a full-body split).
            ['بدي برنامج صدر', 'كم بروتين بالبيض؟', 'training_exercise_recommendation', 'nutrition_lookup'],
            ['اعمللي برنامج MMA', 'كم سعرة في تفاحة؟', 'training_plan_create', 'nutrition_lookup'],
            ['give me a gym plan', 'calories in banana', 'training_plan_create', 'nutrition_lookup'],
            ['بدي برنامج جيم 4 أيام', 'كم بروتين في 200 غرام دجاج؟', 'training_plan_create', 'nutrition_lookup'],
            ['بدي تمرين أكتاف', 'سعرات الحليب', 'training_exercise_recommendation', 'nutrition_lookup'],
            ['4 day bodybuilding program', 'how much protein in rice', 'training_plan_create', 'nutrition_lookup'],
            ['بدي حصة بيت', 'قديش سعرة التونه', 'training_plan_create', 'nutrition_lookup'],
            ['اعمللي برنامج تضخيم جيم', 'كم سعرة بالشوفان', 'training_plan_create', 'nutrition_lookup'],
            ['MMA 3 days', 'protein in chicken breast', 'training_plan_create', 'nutrition_lookup'],
            ['بدي تمرين رجل', 'سعرات البيض', 'training_exercise_recommendation', 'nutrition_lookup'],
        ] as $row) {
            $cases[] = $row;
        }

        // MMA → bodybuilding / gym
        foreach ([
            ['بدي برنامج MMA', 'بدي برنامج تضخيم جيم', 'training_plan_create', 'training_plan_create'],
            ['اعمللي MMA 4 أيام', 'هلق بدي جيم حديد', 'training_plan_create', 'training_plan_create'],
            ['MMA program please', 'bodybuilding 5 day split', 'training_plan_create', 'training_plan_create'],
            ['بدي فنون قتالية', 'بدي برنامج كمال أجسام', 'training_plan_create', 'training_plan_create'],
            ['combat sports plan', 'give me a hypertrophy gym plan', 'training_plan_create', 'training_plan_create'],
        ] as $row) {
            $cases[] = $row;
        }

        // bodybuilding → MMA
        foreach ([
            ['بدي برنامج جيم', 'بدي MMA', 'training_plan_create', 'training_plan_create'],
            ['اعمللي برنامج تضخيم', 'بدي برنامج فنون قتالية', 'training_plan_create', 'training_plan_create'],
            ['4 day gym program', 'I want MMA instead', 'training_plan_create', 'training_plan_create'],
            ['بدي برنامج حديد', 'سوّيلي MMA 3 أيام', 'training_plan_create', 'training_plan_create'],
            ['hypertrophy plan', 'mixed martial arts program', 'training_plan_create', 'training_plan_create'],
        ] as $row) {
            $cases[] = $row;
        }

        // home → gym
        foreach ([
            ['بدي تمرين بالبيت', 'هلق أنا بالنادي بدي برنامج حديد', 'training_plan_create', 'training_plan_create'],
            ['home workout plan', 'I am at the gym now, give me a program', 'training_plan_create', 'training_plan_create'],
            ['بدي برنامج بيت بدون معدات', 'بدي برنامج جيم', 'training_plan_create', 'training_plan_create'],
            ['اعمللي حصة بالمنزل', 'بالنادي بدي برنامج 4 أيام', 'training_plan_create', 'training_plan_create'],
            ['bodyweight home plan', 'gym 5 days program', 'training_plan_create', 'training_plan_create'],
        ] as $row) {
            $cases[] = $row;
        }

        // gym → home
        foreach ([
            ['بدي برنامج جيم', 'هلق أنا بالبيت وما عندي معدات', 'training_plan_create', 'training_plan_create'],
            ['4 day gym plan', 'make it home no equipment', 'training_plan_create', 'training_plan_create'],
            ['اعمللي برنامج نادي', 'بدي بالبيت', 'training_plan_create', 'training_plan_create'],
            ['بدي برنامج حديد', 'بدون معدات بالبيت', 'training_plan_create', 'training_plan_create'],
            ['gym hypertrophy program', 'home bodyweight instead', 'training_plan_create', 'training_plan_create'],
        ] as $row) {
            $cases[] = $row;
        }

        // knowledge → plan
        foreach ([
            ['شو يعني progressive overload؟', 'اعمللي برنامج 4 أيام', 'general_sports_knowledge', 'training_plan_create'],
            ['what is hypertrophy?', 'give me a gym program', 'general_sports_knowledge', 'training_plan_create'],
            ['ليش لازم إحماء؟', 'بدي برنامج MMA', 'general_sports_knowledge', 'training_plan_create'],
            ['what is a warm up?', 'بدي برنامج جيم 3 أيام', 'general_sports_knowledge', 'training_plan_create'],
            ['شو يعني فشل عضلي؟', 'بدي تمرين صدر', 'general_sports_knowledge', 'training_exercise_recommendation'],
        ] as $row) {
            $cases[] = $row;
        }

        // plan → nutrition
        foreach ([
            ['بدي برنامج جيم 4 أيام', 'كم بروتين بالبيض؟', 'training_plan_create', 'nutrition_lookup'],
            ['اعمللي MMA', 'كم سعرة بالتفاحة', 'training_plan_create', 'nutrition_lookup'],
            ['home 3 day plan', 'calories in oats', 'training_plan_create', 'nutrition_lookup'],
            ['بدي برنامج حديد', 'قديش بروتين الدجاج', 'training_plan_create', 'nutrition_lookup'],
            ['create a workout plan', 'protein in milk', 'training_plan_create', 'nutrition_lookup'],
        ] as $row) {
            $cases[] = $row;
        }

        // nutrition → plan
        foreach ([
            ['بدي نظام غذائي للتضخيم', 'بدي برنامج جيم 4 أيام', 'nutrition_plan_create', 'training_plan_create'],
            ['create a bulking meal plan', 'give me a 4 day gym program', 'nutrition_plan_create', 'training_plan_create'],
            ['اعمللي خطة غذائية تنشيف', 'بدي MMA', 'nutrition_plan_create', 'training_plan_create'],
            ['نظام أكل 2500 سعرة', 'بدي برنامج بيت', 'nutrition_plan_create', 'training_plan_create'],
            ['I need a cutting diet', 'chest workout please', 'nutrition_plan_create', 'training_exercise_recommendation'],
        ] as $row) {
            $cases[] = $row;
        }

        // plan → unrelated factual
        foreach ([
            ['بدي برنامج MMA 4 أيام', 'كم سعرة في تفاحة؟', 'training_plan_create', 'nutrition_lookup'],
            ['اعمللي برنامج جيم', 'شو يعني progressive overload؟', 'training_plan_create', 'general_sports_knowledge'],
            ['4 day gym plan', 'how many calories in banana?', 'training_plan_create', 'nutrition_lookup'],
            ['بدي برنامج بيت', 'what is hypertrophy?', 'training_plan_create', 'general_sports_knowledge'],
            ['اعمللي خطة تدريب', 'كم بروتين في البيض؟', 'training_plan_create', 'nutrition_lookup'],
        ] as $row) {
            $cases[] = $row;
        }

        // follow-ups (same domain, not switch)
        foreach ([
            ['اعمللي برنامج 4 أيام جيم', 'خلي اليوم الثالث أصعب', 'training_plan_create', 'training_plan_modify'],
            ['بدي برنامج MMA', 'خليه ساعة', 'training_plan_create', 'training_plan_modify'],
            ['give me a 5 day gym plan', 'make day 2 harder', 'training_plan_create', 'training_plan_modify'],
            ['اعمللي برنامج جيم', 'بدل آخر تمرين باليوم الثاني', 'training_plan_create', 'training_plan_modify'],
            ['بدي برنامج 4 أيام', 'خلي كل حصة 45 دقيقة', 'training_plan_create', 'training_plan_modify'],
        ] as $row) {
            $cases[] = $row;
        }

        // paraphrases / mixed / colloquial
        foreach ([
            ['قديش كالوري التفاح', 'عطيني تمارين صدر', 'nutrition_lookup', 'training_exercise_recommendation'],
            ['سوّيلي برنامج جيم', 'كم بروتين eggs', 'training_plan_create', 'nutrition_lookup'],
            ['جهزلي MMA', 'calories in chicken', 'training_plan_create', 'nutrition_lookup'],
            ['بدي تعمللي برنامج بيت', 'شو سعرات الموز', 'training_plan_create', 'nutrition_lookup'],
            ['اعمل لي workout 4 days gym', 'كم سعرة رز', 'training_plan_create', 'nutrition_lookup'],
            ['I want workout للصدر', 'نظام غذائي للتضخيم', 'training_exercise_recommendation', 'nutrition_plan_create'],
            ['بدي program MMA', 'كم بروتين دجاج 200g', 'training_plan_create', 'nutrition_lookup'],
            ['create لي خطة جيم', 'apple calories', 'training_plan_create', 'nutrition_lookup'],
            ['رتبلي جدول تدريب', 'قديش بروتين البيض', 'training_plan_create', 'nutrition_lookup'],
            ['قسملي الأسبوع جيم', 'سعرات الشوفان 50 غرام', 'training_plan_create', 'nutrition_lookup'],
        ] as $row) {
            $cases[] = $row;
        }

        // ambiguous + safety-ish boundaries
        foreach ([
            ['كم سعرة في تفاحة؟', 'بدي برنامج', 'nutrition_lookup', 'ambiguous_request'],
            ['بدي برنامج MMA', 'بدي برنامج', 'training_plan_create', 'ambiguous_request'],
            ['شو يعني hypertrophy؟', 'بدي برنامج', 'general_sports_knowledge', 'ambiguous_request'],
            ['نظام غذائي للتضخيم', 'بدي برنامج', 'nutrition_plan_create', 'ambiguous_request'],
            ['بدي تمرين صدر', 'بدي برنامج', 'training_exercise_recommendation', 'ambiguous_request'],
        ] as $row) {
            $cases[] = $row;
        }

        return $cases;
    }

    /**
     * @dataProvider transitionProvider
     */
    #[DataProvider('transitionProvider')]
    public function test_topic_transitions(
        string $prev,
        string $curr,
        string $expectedPrevIntent,
        string $expectedCurrIntent,
    ): void {
        $orch = app(SemanticOrchestrator::class);
        $ctx = $this->ctx();

        $r1 = $orch->orchestrate($prev, $ctx, null, 70001);
        $this->assertSame($expectedPrevIntent, $r1['semantic']->intent, "T1 intent for: {$prev}");

        $active = null;
        if (in_array($r1['semantic']->intent, ['training_plan_create', 'training_plan_modify'], true)) {
            $active = [
                'plan' => [
                    'discipline' => $r1['routing']['discipline'] ?? 'bodybuilding',
                    'days_per_week' => 4,
                    'sessions' => [
                        ['day' => 1, 'exercises' => [['exercise_id' => 1]]],
                        ['day' => 2, 'exercises' => [['exercise_id' => 2]]],
                        ['day' => 3, 'exercises' => [['exercise_id' => 3]]],
                    ],
                ],
                'intent' => $r1['training_intent'],
            ];
        }

        $r2 = $orch->orchestrate($curr, $ctx, $active, 70001);
        $this->assertSame($expectedCurrIntent, $r2['semantic']->intent, "T2 intent for: {$curr}");

        $prevTopic = \App\Services\Coach\Semantic\IntentOntology::topicBucket(
            $r1['semantic']->intent,
            $r1['semantic']->domain,
        );
        $currTopic = \App\Services\Coach\Semantic\IntentOntology::topicBucket(
            $r2['semantic']->intent,
            $r2['semantic']->domain,
        );

        if ($prevTopic !== $currTopic && $expectedCurrIntent !== 'training_plan_modify'
            && $expectedCurrIntent !== 'ambiguous_request'
        ) {
            $this->assertTrue(
                ($r2['topic_decision']['is_topic_switch'] ?? false) === true
                || ($r2['topic_decision']['decision'] ?? '') === TopicSwitchDetector::TOPIC_SWITCH
                || ($r2['topic_decision']['decision'] ?? '') === TopicSwitchDetector::AMBIGUOUS,
                "Expected topic_switch for {$prevTopic} → {$currTopic}",
            );
            $this->assertFalse($r2['context_relevance']['use_active_plan'] ?? true);
            $this->assertFalse($r2['follow_up']['is_follow_up'] ?? true);
        }

        if ($expectedCurrIntent === 'training_plan_modify') {
            $this->assertTrue($r2['follow_up']['is_follow_up'] ?? false);
            $this->assertTrue($r2['context_relevance']['use_active_plan'] ?? false);
        }

        if ($expectedCurrIntent === 'nutrition_lookup') {
            $this->assertTrue($r2['routing']['is_food_lookup'] ?? false);
            $this->assertFalse($r2['routing']['is_plan_request'] ?? true);
        }

        if ($expectedCurrIntent === 'training_plan_create') {
            $this->assertTrue($r2['routing']['is_plan_request'] ?? false);
            $this->assertFalse($r2['routing']['is_food_lookup'] ?? true);
            $this->assertFalse($r2['routing']['is_nutrition_plan'] ?? true);
        }
    }

    public function test_multi_turn_conversation_a(): void
    {
        $orch = app(SemanticOrchestrator::class);
        $ctx = $this->ctx();
        $cid = 80001;
        $active = null;

        $turns = [
            ['كم سعرة بالتفاحة؟', 'nutrition_lookup'],
            ['بدي تمرين صدر', 'training_exercise_recommendation'],
            ['خليه بالبيت', 'training_plan_create'], // env change may be plan/follow
            ['لا هلق أنا بالنادي', 'training_plan_create'],
            ['خليه 45 دقيقة', 'training_plan_modify'],
        ];

        foreach ($turns as $i => [$msg, $want]) {
            $r = $orch->orchestrate($msg, $ctx, $active, $cid);
            // Soft for env-only turns that may classify as plan create.
            if ($i >= 2 && $i <= 3) {
                $this->assertContains($r['semantic']->intent, [
                    'training_plan_create',
                    'training_plan_modify',
                    'training_exercise_recommendation',
                ], "turn {$i}: {$msg}");
            } else {
                $this->assertSame($want, $r['semantic']->intent, "turn {$i}: {$msg}");
            }
            if ($r['semantic']->intent === 'training_plan_create' || ($r['follow_up']['is_follow_up'] ?? false)) {
                $active = [
                    'plan' => [
                        'discipline' => $r['routing']['discipline'] ?? 'bodybuilding',
                        'days_per_week' => 4,
                        'requested_session_duration_minutes' => 45,
                        'sessions' => [
                            ['day' => 1, 'exercises' => [['exercise_id' => 1]]],
                            ['day' => 2, 'exercises' => [['exercise_id' => 2]]],
                            ['day' => 3, 'exercises' => [['exercise_id' => 3]]],
                        ],
                    ],
                    'intent' => $r['training_intent'],
                ];
            }
            if ($i === 0) {
                $this->assertSame('nutrition', $r['topic_decision']['current_topic'] ?? null);
            }
            if ($i === 1) {
                $this->assertTrue($r['topic_decision']['is_topic_switch'] ?? false);
            }
        }
    }

    public function test_multi_turn_conversation_b(): void
    {
        $orch = app(SemanticOrchestrator::class);
        $ctx = $this->ctx();
        $cid = 80002;
        $active = null;

        $r1 = $orch->orchestrate('بدي برنامج MMA 4 أيام', $ctx, null, $cid);
        $this->assertSame('training_plan_create', $r1['semantic']->intent);
        $active = [
            'plan' => [
                'discipline' => 'mma',
                'days_per_week' => 4,
                'sessions' => [
                    ['day' => 1, 'exercises' => [['exercise_id' => 1], ['exercise_id' => 2]]],
                    ['day' => 2, 'exercises' => [['exercise_id' => 3], ['exercise_id' => 4]]],
                    ['day' => 3, 'exercises' => [['exercise_id' => 5]]],
                ],
            ],
            'intent' => $r1['training_intent'],
        ];

        $r2 = $orch->orchestrate('خلي اليوم الثالث أصعب', $ctx, $active, $cid);
        $this->assertSame('training_plan_modify', $r2['semantic']->intent);
        $this->assertTrue($r2['follow_up']['is_follow_up'] ?? false);
        $this->assertTrue($r2['context_relevance']['use_active_plan'] ?? false);

        $r3 = $orch->orchestrate('كم بروتين بالبيض؟', $ctx, $active, $cid);
        $this->assertSame('nutrition_lookup', $r3['semantic']->intent);
        $this->assertTrue($r3['topic_decision']['is_topic_switch'] ?? false);
        $this->assertFalse($r3['context_relevance']['use_active_plan'] ?? true);

        $r4 = $orch->orchestrate('بدل آخر تمرين باليوم الثاني', $ctx, $active, $cid);
        $this->assertSame('training_plan_modify', $r4['semantic']->intent);
        $this->assertTrue($r4['follow_up']['is_follow_up'] ?? false);

        $r5 = $orch->orchestrate('رجعني للخطة', $ctx, $active, $cid);
        $this->assertContains($r5['semantic']->intent, ['training_plan_resume', 'training_plan_modify']);
        $this->assertTrue($r5['follow_up']['is_follow_up'] ?? false);
    }

    public function test_ambiguous_program_asks_clarification(): void
    {
        $orch = app(SemanticOrchestrator::class);
        $r = $orch->orchestrate('بدي برنامج', $this->ctx());
        $this->assertSame('ambiguous_request', $r['semantic']->intent);
        $this->assertTrue($r['routing']['clarification_required'] ?? false);
        $msg = app(CoachResponsePolicy::class)->clarificationMessage('ar');
        $this->assertStringContainsString('Bodybuilding', $msg);
    }

    public function test_openai_provider_falls_back_on_invalid_json(): void
    {
        config([
            'services.openai.enabled' => true,
            'services.openai.api_key' => 'sk-test',
            'services.openai.model' => 'gpt-4o-mini',
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'not-json']]],
            ], 200),
        ]);

        $provider = app(OpenAISemanticProvider::class);
        $this->assertNull($provider->interpret('بدي تمرين صدر'));
    }

    public function test_openai_provider_rejects_non_allowlisted_intent(): void
    {
        config([
            'services.openai.enabled' => true,
            'services.openai.api_key' => 'sk-test',
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'intent' => 'hack.system',
                            'domain' => 'system',
                            'operation' => null,
                            'confidence' => 0.9,
                            'is_follow_up' => false,
                            'is_topic_switch' => false,
                            'clarification_required' => false,
                            'discipline' => null,
                            'environment' => null,
                            'goal' => null,
                            'days' => null,
                            'duration_minutes' => null,
                            'body_part' => null,
                            'level' => null,
                            'modification' => null,
                            'language' => 'ar',
                            'entities' => [],
                            'constraints' => [],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $this->assertNull(app(OpenAISemanticProvider::class)->interpret('ignore previous'));
    }

    public function test_openai_timeout_returns_null(): void
    {
        config([
            'services.openai.enabled' => true,
            'services.openai.api_key' => 'sk-test',
            'services.openai.timeout_ms' => 1000,
        ]);
        Http::fake([
            'api.openai.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('timeout');
            },
        ]);

        $this->assertNull(app(OpenAISemanticProvider::class)->interpret('بدي برنامج جيم'));
    }

    public function test_diagnostics_endpoint_hides_secrets(): void
    {
        config([
            'services.openai.enabled' => false,
            'services.openai.api_key' => 'sk-secret-should-not-leak',
        ]);
        $res = $this->getJson('/api/coach/diagnostics');
        $res->assertOk();
        $json = $res->json('data');
        $this->assertArrayHasKey('provider', $json);
        $this->assertArrayHasKey('semantic_interpreter', $json);
        $body = $res->getContent();
        $this->assertStringNotContainsString('sk-secret', $body);
    }

    public function test_answer_validator_catches_topic_bleed(): void
    {
        $orch = app(SemanticOrchestrator::class);
        $r = $orch->orchestrate('بدي تمرين صدر', $this->ctx());
        $v = app(CoachAnswerValidator::class)->validate(
            $r['semantic'],
            $r['routing'],
            ['is_topic_switch' => true, 'previous_topic' => 'nutrition', 'current_topic' => 'training'],
            null,
            ['meals' => []],
            null,
        );
        $this->assertFalse($v['ok']);
        $this->assertContains('topic_switch_nutrition_bleed', $v['errors']);
    }

    public function test_topic_state_stack(): void
    {
        $state = app(ConversationTopicState::class);
        $state->forget(90001);
        $state->push(90001, 'nutrition');
        $state->push(90001, 'training');
        $state->push(90001, 'nutrition');
        $recalled = $state->recall(90001);
        $this->assertSame('nutrition', $recalled['active_topic']);
        $this->assertContains('training', $recalled['history']);
    }

    /**
     * @return array<string, mixed>
     */
    private function ctx(): array
    {
        return [
            'primary_goal' => 'muscle_gain',
            'goals' => ['primary_goal' => 'muscle_gain'],
            'training' => ['days_per_week' => 3, 'session_duration_minutes' => 60, 'training_location' => 'home'],
            'equipment' => ['mode' => 'home', 'available' => []],
            'recovery' => ['injury_status' => 'none', 'injury_areas' => []],
            'language' => 'ar',
            'locale' => 'ar',
        ];
    }
}
