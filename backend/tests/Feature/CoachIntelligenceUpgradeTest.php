<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachFollowUpInterpreter;
use App\Services\Coach\CoachPlanContextStore;
use App\Services\Coach\Nutrition\NutritionLookupService;
use App\Services\Coach\Nutrition\NutritionQueryExtractor;
use App\Services\Coach\TrainingIntentExtractor;
use App\Services\Coach\TrainingPlanEngine;
use Database\Seeders\NutritionFoodSeeder;
use Database\Seeders\TrainingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coach intelligence upgrade — explicit NL overrides, duration, MMA, food fallthrough, follow-ups.
 */
class CoachIntelligenceUpgradeTest extends TestCase
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

    public function test_1_english_4day_mma_120_minutes_intent(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract(
            'Give me a 4-day MMA training plan, 2 hours every day.',
            $this->homeContext(),
        );

        $this->assertTrue($intent['is_plan_request']);
        $this->assertSame('training_plan', $intent['intent']);
        $this->assertSame('mma', $intent['discipline']);
        $this->assertSame(4, $intent['days_per_week']);
        $this->assertSame(120, $intent['session_duration_minutes']);
        $this->assertTrue($intent['discipline_explicit']);
        $this->assertTrue($intent['days_explicit']);
        $this->assertTrue($intent['duration_explicit']);
        $this->assertSame('gym', $intent['equipment_mode']);
    }

    public function test_1b_english_mma_plan_engine_respects_constraints(): void
    {
        $result = app(TrainingPlanEngine::class)->generateFromMessage(
            'Give me a 4-day MMA training plan, 2 hours every day.',
            $this->homeContext(),
        );

        $this->assertNotNull($result['plan']);
        $this->assertSame('mma', $result['plan']['discipline']);
        $this->assertSame(4, $result['plan']['days_per_week']);
        $this->assertSame(120, $result['plan']['requested_session_duration_minutes']);
        $this->assertGreaterThanOrEqual(84, (int) $result['plan']['estimated_session_duration_minutes']);
        foreach ($result['plan']['sessions'] as $session) {
            $this->assertNotEmpty($session['exercises']);
            foreach ($session['exercises'] as $ex) {
                $this->assertTrue($ex['grounded']);
                $this->assertIsInt($ex['exercise_id']);
            }
        }
    }

    public function test_2_arabic_4day_mma_two_hours_intent(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract(
            'اعطيني خطة تدريب فنون قتالية مختلطة من اربع ايام ومدة التمرين في كل يوم ساعتين',
            $this->homeContext(),
        );

        $this->assertTrue($intent['is_plan_request']);
        $this->assertSame('mma', $intent['discipline']);
        $this->assertSame(4, $intent['days_per_week']);
        $this->assertSame(120, $intent['session_duration_minutes']);
    }

    public function test_3_profile_home_gym_bodybuilding_overrides(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract(
            'Give me a gym bodybuilding plan.',
            $this->homeContext(),
        );

        $this->assertSame('bodybuilding', $intent['discipline']);
        $this->assertSame('gym', $intent['equipment_mode']);
        $this->assertTrue($intent['discipline_explicit']);
    }

    public function test_4_profile_home_5day_mma_overrides(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract(
            'Give me a 5-day MMA plan.',
            $this->homeContext(),
        );

        $this->assertSame('mma', $intent['discipline']);
        $this->assertSame(5, $intent['days_per_week']);
        $this->assertTrue($intent['days_explicit']);

        $plan = app(TrainingPlanEngine::class)->generateFromMessage(
            'Give me a 5-day MMA plan.',
            $this->homeContext(),
        )['plan'];
        $this->assertNotNull($plan);
        $this->assertSame('mma', $plan['discipline']);
        $this->assertSame(5, $plan['days_per_week']);
    }

    public function test_5_one_apple_calories_lookup_or_not_hard_fail(): void
    {
        $extractor = app(NutritionQueryExtractor::class);
        $items = $extractor->extractFoodItems('How many calories are in one apple?');
        $this->assertNotEmpty($items);
        $this->assertSame('apple', mb_strtolower($items[0]['query']));
        $this->assertSame(1.0, $items[0]['quantity']);
        $this->assertSame('piece', $items[0]['unit']);

        $result = app(NutritionLookupService::class)->handleMessage(
            'How many calories are in one apple?',
            'en',
        );
        $this->assertNotNull($result);
        // Apple exists in OCTA FIT fixture — exact path preferred.
        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['totals']['calories_kcal']);
        $this->assertStringNotContainsStringIgnoringCase(
            'Food not found',
            app(NutritionLookupService::class)->formatMessage($result, 'en'),
        );
    }

    public function test_5b_unknown_food_falls_through_for_coach_path(): void
    {
        $result = app(NutritionLookupService::class)->handleMessage(
            'How many calories are in 100g unicorn meat?',
            'en',
        );
        $this->assertNotNull($result);
        $this->assertFalse($result['ok']);
        $this->assertTrue($result['fallthrough_general_knowledge'] ?? false);

        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'Approximate calories vary; exact OCTA FIT data is unavailable for that food.',
                'provider' => 'mock',
                'language' => 'en',
            ], 200),
        ]);

        $user = $this->makeUser('intel-apple-fallback@test.com');
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations')->json('data.id');
        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'How many calories are in 100g unicorn meat?',
            'language' => 'en',
        ]);
        $response->assertCreated();
        $this->assertNull($response->json('data.nutrition'));
        $this->assertStringNotContainsString(
            'Food not found or could not be calculated from the OCTA FIT food database',
            (string) $response->json('data.assistant_message.content'),
        );
    }

    public function test_6_200g_chicken_breast_quantity_path(): void
    {
        $items = app(NutritionQueryExtractor::class)->extractFoodItems(
            'How many calories are in 200g chicken breast?',
        );
        $this->assertNotEmpty($items);
        $this->assertSame(200.0, $items[0]['quantity']);
        $this->assertSame('g', $items[0]['unit']);
        $this->assertStringContainsString('chicken', mb_strtolower($items[0]['query']));

        $result = app(NutritionLookupService::class)->handleMessage(
            'How many calories are in 200g chicken breast?',
            'en',
        );
        $this->assertNotNull($result);
        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['totals']['calories_kcal']);
    }

    public function test_9_follow_up_duration_90_minutes(): void
    {
        $engine = app(TrainingPlanEngine::class);
        $first = $engine->generateFromMessage(
            'Give me a 4-day MMA training plan, 2 hours every day.',
            $this->homeContext(),
        );
        $this->assertNotNull($first['plan']);
        $this->assertSame(120, $first['plan']['requested_session_duration_minutes']);

        $follow = app(CoachFollowUpInterpreter::class)->interpret(
            'Make my MMA plan 90 minutes instead of 2 hours.',
            ['plan' => $first['plan'], 'intent' => $first['intent']],
        );
        $this->assertTrue($follow['is_follow_up']);
        $this->assertSame('update_duration', $follow['action']);
        $this->assertSame(90, $follow['duration_minutes']);

        $second = $engine->generate($follow['merged_intent'], $this->homeContext());
        $this->assertNotNull($second['plan']);
        $this->assertSame(90, $second['plan']['requested_session_duration_minutes']);
        $this->assertSame('mma', $second['plan']['discipline']);
        $this->assertSame(4, $second['plan']['days_per_week']);
    }

    public function test_10_follow_up_intensify_day_3(): void
    {
        $engine = app(TrainingPlanEngine::class);
        $first = $engine->generateFromMessage(
            'Give me a 4-day MMA training plan, 2 hours every day.',
            $this->homeContext(),
        );
        $this->assertNotNull($first['plan']);
        $day3Before = $first['plan']['sessions'][2]['exercises'][0]['sets'] ?? null;
        $this->assertNotNull($day3Before);

        $follow = app(CoachFollowUpInterpreter::class)->interpret(
            'Make day 3 harder.',
            ['plan' => $first['plan'], 'intent' => $first['intent']],
        );
        $this->assertTrue($follow['is_follow_up']);
        $this->assertSame('intensify_day', $follow['action']);
        $this->assertSame(3, $follow['day']);

        $updated = $engine->intensifyDay($first['plan'], 3);
        $day3After = $updated['sessions'][2]['exercises'][0]['sets'] ?? null;
        $this->assertSame((int) $day3Before + 1, (int) $day3After);
        $this->assertSame(4, count($updated['sessions']));
        $this->assertSame('mma', $updated['discipline']);
        $this->assertStringContainsString('harder', mb_strtolower($updated['sessions'][2]['title'] ?? ''));
    }

    public function test_plan_context_store_roundtrip(): void
    {
        $store = app(CoachPlanContextStore::class);
        $store->remember(42, ['id' => 'p1', 'discipline' => 'mma'], ['discipline' => 'mma', 'days_per_week' => 4]);
        $recalled = $store->recall(42);
        $this->assertSame('mma', $recalled['plan']['discipline']);
        $this->assertSame(4, $recalled['intent']['days_per_week']);
    }

    public function test_coach_api_4day_mma_two_hours(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'Here is your MMA plan.',
                'provider' => 'mock',
                'language' => 'en',
                'grounded_exercises' => [],
            ], 200),
        ]);

        $user = $this->makeUser('intel-mma-api@test.com', [
            'training_location' => 'home',
            'primary_goal' => 'general_fitness',
        ]);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations')->json('data.id');
        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'Give me a 4-day MMA training plan, 2 hours every day.',
            'language' => 'en',
        ]);
        $response->assertCreated();
        $plan = $response->json('data.training_plan');
        $this->assertIsArray($plan);
        $this->assertSame('mma', $plan['discipline']);
        $this->assertSame(4, $plan['days_per_week']);
        $this->assertSame(120, $plan['requested_session_duration_minutes']);
    }

    /**
     * @return array<string, mixed>
     */
    private function homeContext(): array
    {
        return [
            'goals' => ['primary_goal' => 'general_fitness'],
            'training' => [
                'fitness_level' => 'intermediate',
                'training_location' => 'home',
                'training_frequency_per_week' => 3,
                'session_duration_minutes' => 45,
            ],
            'primary_goal' => 'general_fitness',
            'fitness_level' => 'intermediate',
            'training_location' => 'home',
            'training_frequency_per_week' => 3,
            'session_duration_minutes' => 45,
            'memory_context' => [
                'count' => 1,
                'memories' => [
                    [
                        'category' => 'equipment',
                        'key' => 'home_only',
                        'value' => ['mode' => 'home'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $profileOverrides
     */
    private function makeUser(string $email, array $profileOverrides = []): User
    {
        $user = User::query()->create([
            'name' => 'Intel Tester',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 2,
            'is_active' => true,
        ]);
        Profile::query()->create(array_merge([
            'user_id' => $user->id,
            'locale' => 'en',
            'height_cm' => 178,
            'weight_kg' => 80,
            'fitness_level' => 'intermediate',
            'training_goal' => 'general_fitness',
            'training_experience' => '1_3_years',
            'training_frequency' => 3,
            'training_location' => 'home',
            'injury_status' => 'none',
            'profile_completed_at' => now(),
        ], $profileOverrides));

        return $user->fresh(['profile']);
    }
}
