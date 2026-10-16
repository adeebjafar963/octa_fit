<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\Semantic\SemanticOrchestrator;
use App\Services\Coach\TrainingPlanEngine;
use Database\Seeders\NutritionFoodSeeder;
use Database\Seeders\TrainingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end: natural message → semantic → capability route → TrainingPlanEngine → structured plan.
 * Must NOT silently degrade to generic Coach intro / related exercises.
 */
class CoachTrainingPlanRoutingE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->insert([
            ['id' => 1, 'name' => 'admin', 'display_name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'user', 'display_name' => 'User', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->seed(TrainingSeeder::class);
        $this->seed(NutritionFoodSeeder::class);
    }

    /** Live-failure message that previously returned generic intro. */
    public function test_live_failure_arabic_mma_gym_session_creates_plan(): void
    {
        $msg = 'بدي حصة MMA بالنادي لمدة ساعتين لمدة 4 أيام بالأسبوع.';
        $this->assertFullPipelineCreatesMmaPlan($msg);
    }

    public function test_unseen_paraphrases_create_mma_plans(): void
    {
        $phrases = [
            'رتبلي تدريب MMA بالنادي على أربع أيام، وكل حصة تقريبًا ساعتين.',
            'بدي أتدرب فنون قتالية مختلطة 4 مرات بالأسبوع بالنادي، خلي الجلسة طويلة حوالي ساعتين.',
            'قسملي تدريبي القتالي لأربع حصص أسبوعية بالنادي، ساعتين للحصة.',
            'اعمللي جدول MMA أربع أيام، عندي وقت ساعتين كل يوم.',
            'بدي برنامج للنادي يركز على الـMMA أربع أيام بالأسبوع.',
            'make me a four day MMA gym program, about two hours each day.',
            'I train MMA at the gym four days a week. Build my program with two-hour sessions.',
        ];
        foreach ($phrases as $message) {
            $needsDuration = (bool) preg_match('/(ساعت|ساعتين|two hour|120)/ui', $message);
            $this->assertFullPipelineCreatesMmaPlan($message, requireDuration: $needsDuration);
        }
    }

    public function test_http_path_returns_structured_training_plan_not_generic_intro(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'أنا مدرب أوكتا فيت — هذه تمارين ذات صلة فقط.',
                'provider' => 'mock',
                'language' => 'ar',
                'grounded_exercises' => [],
            ], 200),
            '*/interpret' => Http::response(null, 503),
        ]);

        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations', [])->json('data.id');

        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'بدي حصة MMA بالنادي لمدة ساعتين لمدة 4 أيام بالأسبوع.',
            'language' => 'ar',
        ])->assertCreated();

        $plan = $response->json('data.training_plan');
        $this->assertNotNull($plan, 'HTTP path must return structured training_plan');
        $this->assertSame('mma', $plan['discipline']);
        $this->assertSame(4, (int) $plan['days_per_week']);
        $this->assertSame(120, (int) $plan['requested_session_duration_minutes']);
        $this->assertSame(4, count($plan['sessions']));

        $content = (string) $response->json('data.assistant_message.content');
        $this->assertStringNotContainsString('أنا مدرب أوكتا فيت', $content);
        // Plan formatting should mention sessions / MMA, not only related exercises.
        $this->assertTrue(
            str_contains(mb_strtolower($content), 'mma')
            || str_contains($content, 'يوم')
            || str_contains($content, 'جلسة')
            || str_contains(mb_strtolower($content), 'session')
            || isset($plan['sessions'][0]['exercises'][0]['exercise_id']),
        );
        foreach ($plan['sessions'] as $session) {
            foreach ($session['exercises'] as $ex) {
                $this->assertArrayHasKey('exercise_id', $ex);
                $this->assertIsInt($ex['exercise_id']);
            }
        }
    }

    public function test_nutrition_and_knowledge_still_separate(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'Progressive overload means gradually increasing training stress.',
                'provider' => 'mock',
                'language' => 'en',
                'grounded_exercises' => [],
            ], 200),
            '*/interpret' => Http::response(null, 503),
        ]);

        $orch = app(SemanticOrchestrator::class);
        $food = $orch->orchestrate('التفاحة كم سعرة؟', $this->homeContext());
        $this->assertSame('food_lookup', $food['routing']['intent']);
        $this->assertFalse($food['routing']['is_plan_request']);

        $know = $orch->orchestrate('شو هو progressive overload؟', $this->homeContext());
        $this->assertSame('general_sports_question', $know['routing']['intent']);
        $this->assertFalse($know['routing']['is_plan_request']);

        $bb = $orch->orchestrate('بدي برنامج كمال أجسام 5 أيام بالنادي', $this->homeContext());
        $this->assertTrue($bb['routing']['is_plan_request']);
        $this->assertSame('gym', $bb['routing']['equipment_mode']);
    }

    private function assertFullPipelineCreatesMmaPlan(string $message, bool $requireDuration = true): void
    {
        $ctx = $this->homeContext();
        $orch = app(SemanticOrchestrator::class);
        $result = $orch->orchestrate($message, $ctx);

        $this->assertSame('training_plan_create', $result['semantic']->intent, $message);
        $this->assertTrue($result['routing']['is_plan_request'], $message);
        $this->assertContains('training_plan', $result['routing']['capabilities'] ?? [], $message);
        $this->assertSame('mma', $result['routing']['discipline'] ?? $result['training_intent']['discipline'] ?? null, $message);
        $this->assertSame('gym', $result['routing']['equipment_mode'] ?? $result['training_intent']['equipment_mode'] ?? null, $message);
        $this->assertSame(4, (int) ($result['routing']['days'] ?? $result['training_intent']['days_per_week'] ?? 0), $message);
        if ($requireDuration) {
            $this->assertSame(120, (int) ($result['routing']['duration_minutes'] ?? $result['training_intent']['session_duration_minutes'] ?? 0), $message);
        }

        $intent = $result['training_intent'];
        $intent['is_plan_request'] = true;
        $engine = app(TrainingPlanEngine::class);
        $planResult = $engine->generate($intent, $ctx, str_contains($message, 'MMA') || preg_match('/\p{Arabic}/u', $message) ? 'ar' : 'en');

        $this->assertNotNull($planResult['plan'], 'TrainingPlanEngine must execute for: '.$message);
        $this->assertSame('mma', $planResult['plan']['discipline'], $message);
        $this->assertSame(4, (int) $planResult['plan']['days_per_week'], $message);
        if ($requireDuration) {
            $this->assertSame(120, (int) $planResult['plan']['requested_session_duration_minutes'], $message);
        }
        $this->assertCount(4, $planResult['plan']['sessions'], $message);
    }

    /** @return array<string, mixed> */
    private function homeContext(): array
    {
        return [
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
    }

    private function makeUser(): User
    {
        $user = User::query()->create([
            'name' => 'Plan Route Tester',
            'email' => 'plan-route-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role_id' => 2,
        ]);
        Profile::query()->create([
            'user_id' => $user->id,
            'locale' => 'ar',
            'weight_kg' => 75,
            'height_cm' => 175,
            'age' => 28,
            'gender' => 'male',
            'training_goal' => 'general_fitness',
            'training_location' => 'home',
            'available_days_per_week' => 3,
            'session_duration_minutes' => 60,
        ]);

        return $user->fresh('profile');
    }
}
