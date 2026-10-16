<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\NutritionPreference;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\Nutrition\CalorieTargetCalculator;
use App\Services\Coach\Nutrition\FoodConstraintService;
use App\Services\Coach\Nutrition\MacroTargetCalculator;
use App\Services\Coach\Nutrition\NutritionIntentExtractor;
use App\Services\Coach\Nutrition\NutritionPlanEngine;
use App\Services\Coach\Nutrition\NutritionPlanValidator;
use App\Services\Coach\Nutrition\NutritionPreferenceService;
use Database\Seeders\NutritionFoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachNutritionPlanPhase11eTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->insert([
            ['id' => 1, 'name' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'user', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->seed(NutritionFoodSeeder::class);
    }

    public function test_intent_bulking_vs_lookup(): void
    {
        $extractor = app(NutritionIntentExtractor::class);
        $plan = $extractor->extract('Create me a bulking meal plan.');
        $this->assertTrue($plan['is_nutrition_plan_request']);
        $this->assertSame('muscle_gain', $plan['goal']);

        $lookupIntent = $extractor->extract('How many calories are in 200g cooked rice?');
        $this->assertFalse($lookupIntent['is_nutrition_plan_request']);
    }

    public function test_calorie_and_macro_targets_deterministic(): void
    {
        $ctx = $this->sampleContext();
        $cal = app(CalorieTargetCalculator::class)->calculate($ctx, 'muscle_gain');
        $this->assertTrue($cal['ok']);
        $this->assertGreaterThan(1400, $cal['calorie_target']);
        $this->assertLessThanOrEqual(4500, $cal['calorie_target']);
        $this->assertNotEmpty($cal['assumptions']);

        $macros = app(MacroTargetCalculator::class)->calculate((int) $cal['calorie_target'], 'muscle_gain', $ctx);
        $this->assertTrue($macros['ok']);
        $this->assertGreaterThan(0, $macros['protein_g']);
        $this->assertGreaterThanOrEqual(0, $macros['carbohydrates_g']);
        $this->assertGreaterThan(0, $macros['fat_g']);

        $sum = $macros['protein_kcal'] + $macros['carbohydrates_kcal'] + $macros['fat_kcal'];
        $this->assertEqualsWithDelta((float) $cal['calorie_target'], $sum, 15.0);
    }

    public function test_explicit_calorie_target(): void
    {
        $cal = app(CalorieTargetCalculator::class)->calculate($this->sampleContext(), 'maintenance', 2500);
        $this->assertSame(2500, $cal['calorie_target']);
        $this->assertSame('explicit_override', $cal['activity_category']);
    }

    public function test_missing_profile_uses_documented_fallbacks(): void
    {
        $cal = app(CalorieTargetCalculator::class)->calculate(['available' => true], 'maintenance');
        $this->assertTrue($cal['ok']);
        $this->assertTrue(collect($cal['assumptions'])->contains(fn ($a) => str_contains($a, 'fallback')));
    }

    public function test_bulking_plan_acceptance(): void
    {
        $user = $this->makeUser([
            'training_goal' => 'muscle_gain',
            'fitness_level' => 'intermediate',
            'training_frequency' => 4,
            'meals_per_day' => 4,
            'weight_kg' => 80,
            'height_cm' => 180,
            'age' => 28,
            'gender' => 'male',
        ]);

        $result = app(NutritionPlanEngine::class)->generateFromMessage(
            $user,
            'Create me a bulking meal plan.',
            app(\App\Services\UserAiContextBuilder::class)->forCoach($user),
        );

        $this->assertSame([], $result['errors'], json_encode($result['errors']));
        $plan = $result['plan'];
        $this->assertNotNull($plan);
        $this->assertSame('11e.1', $plan['schema_version']);
        $this->assertSame('muscle_gain', $plan['goal']);
        $this->assertSame(4, $plan['meals_per_day']);
        $this->assertTrue($plan['validated'] ?? false);
        $this->assertPlanFullyGrounded($plan);
        $this->assertEqualsWithDelta(
            (float) $plan['calorie_target'],
            (float) $plan['days'][0]['daily_totals']['calories_kcal'],
            (float) config('nutrition_plan.calorie_tolerance_kcal') + 1,
        );
    }

    public function test_cutting_plan_not_extreme(): void
    {
        $user = $this->makeUser([
            'training_goal' => 'fat_loss',
            'weight_kg' => 80,
            'height_cm' => 180,
            'age' => 28,
            'gender' => 'male',
            'activity_level' => 'moderately_active',
        ]);
        $ctx = app(\App\Services\UserAiContextBuilder::class)->forCoach($user);
        $result = app(NutritionPlanEngine::class)->generateFromMessage($user, 'Create me a cutting meal plan.', $ctx);
        $this->assertNotNull($result['plan'], json_encode($result['errors']));
        $target = (int) $result['plan']['calorie_target'];
        $this->assertGreaterThanOrEqual(1400, $target);
        $tdee = (float) ($result['plan']['energy']['tdee_kcal'] ?? 0);
        if ($tdee > 0) {
            $this->assertLessThan($tdee, $target);
            $this->assertGreaterThan($tdee * 0.7, $target); // not an extreme deficit
        }
    }

    public function test_maintenance_and_multi_day(): void
    {
        $user = $this->makeUser(['weight_kg' => 70, 'height_cm' => 170, 'age' => 30, 'gender' => 'female']);
        $result = app(NutritionPlanEngine::class)->generateFromMessage(
            $user,
            'Give me a 3-day maintenance meal plan with 3 meals',
            app(\App\Services\UserAiContextBuilder::class)->forCoach($user),
        );
        $this->assertNotNull($result['plan'], json_encode($result['errors']));
        $this->assertSame(3, $result['plan']['duration_days']);
        $this->assertSame(3, $result['plan']['meals_per_day']);
        $this->assertCount(3, $result['plan']['days']);
    }

    public function test_allergy_hard_exclusion_peanut(): void
    {
        $user = $this->makeUser(['weight_kg' => 75, 'height_cm' => 175, 'age' => 25, 'gender' => 'male']);
        NutritionPreference::query()->create([
            'user_id' => $user->id,
            'allergies' => ['peanut'],
            'disliked' => [],
            'excluded_foods' => [],
        ]);

        $result = app(NutritionPlanEngine::class)->generateFromMessage(
            $user,
            'Create me a 2500 calorie meal plan.',
            app(\App\Services\UserAiContextBuilder::class)->forCoach($user),
        );
        $this->assertNotNull($result['plan'], json_encode($result['errors']));
        $this->assertPlanHasNoAllergen($result['plan'], 'peanut');

        // Regeneration after stating allergy.
        $result2 = app(NutritionPlanEngine::class)->generateFromMessage(
            $user,
            'I am allergic to peanuts now.',
            app(\App\Services\UserAiContextBuilder::class)->forCoach($user),
        );
        $this->assertNotNull($result2['plan'], json_encode($result2['errors']));
        $this->assertGreaterThan(0, (int) $result['plan']['version']);
        $this->assertSame((int) $result['plan']['version'] + 1, (int) $result2['plan']['version']);
        $this->assertPlanHasNoAllergen($result2['plan'], 'peanut');
        $prefs = app(NutritionPreferenceService::class)->bucketsForUser($user);
        $this->assertContains('peanut', $prefs['allergies']);
    }

    public function test_dislike_eggs_replaces_with_real_food(): void
    {
        $user = $this->makeUser(['weight_kg' => 75, 'height_cm' => 175, 'age' => 25, 'gender' => 'male', 'meals_per_day' => 4]);
        $engine = app(NutritionPlanEngine::class);
        $ctx = app(\App\Services\UserAiContextBuilder::class)->forCoach($user);

        $first = $engine->generateFromMessage($user, 'Create me a bulking meal plan.', $ctx);
        $this->assertNotNull($first['plan']);

        $second = $engine->generateFromMessage($user, "I don't like eggs.", $ctx);
        $this->assertNotNull($second['plan'], json_encode($second['errors']));
        $this->assertPlanHasNoNameContains($second['plan'], 'egg');
        $this->assertPlanFullyGrounded($second['plan']);
    }

    public function test_replace_chicken(): void
    {
        $user = $this->makeUser(['weight_kg' => 75, 'height_cm' => 175, 'age' => 25, 'gender' => 'male']);
        $engine = app(NutritionPlanEngine::class);
        $ctx = app(\App\Services\UserAiContextBuilder::class)->forCoach($user);

        $first = $engine->generateFromMessage($user, 'Create me a maintenance meal plan.', $ctx);
        $this->assertNotNull($first['plan']);
        $this->assertTrue($this->planContainsName($first['plan'], 'chicken'));

        $second = $engine->generateFromMessage($user, 'Replace chicken.', $ctx);
        $this->assertNotNull($second['plan'], json_encode($second['errors']));
        $this->assertFalse($this->planContainsName($second['plan'], 'chicken'));
        $this->assertPlanFullyGrounded($second['plan']);
        $this->assertSame((int) $first['plan']['calorie_target'], (int) $second['plan']['calorie_target']);
    }

    public function test_fake_food_id_rejected_by_validator(): void
    {
        $plan = [
            'schema_version' => '11e.1',
            'calorie_target' => 2000,
            'macro_targets' => ['protein_g' => 100, 'carbohydrates_g' => 200, 'fat_g' => 60],
            'meals_per_day' => 2,
            'days' => [[
                'day_number' => 1,
                'meals' => [
                    [
                        'meal_number' => 1,
                        'name' => 'Breakfast',
                        'foods' => [[
                            'food_id' => 999999,
                            'quantity' => 100,
                            'unit' => 'g',
                            'normalized_grams' => 100,
                            'calories_kcal' => 100,
                            'protein_g' => 10,
                            'carbohydrates_g' => 10,
                            'fat_g' => 1,
                        ]],
                        'meal_totals' => ['calories_kcal' => 100, 'protein_g' => 10, 'carbohydrates_g' => 10, 'fat_g' => 1],
                    ],
                    [
                        'meal_number' => 2,
                        'name' => 'Lunch',
                        'foods' => [[
                            'food_id' => 999999,
                            'quantity' => 100,
                            'unit' => 'g',
                            'normalized_grams' => 100,
                            'calories_kcal' => 100,
                            'protein_g' => 10,
                            'carbohydrates_g' => 10,
                            'fat_g' => 1,
                        ]],
                        'meal_totals' => ['calories_kcal' => 100, 'protein_g' => 10, 'carbohydrates_g' => 10, 'fat_g' => 1],
                    ],
                ],
                'daily_totals' => ['calories_kcal' => 2000, 'protein_g' => 100, 'carbohydrates_g' => 200, 'fat_g' => 60],
            ]],
        ];

        $validated = app(NutritionPlanValidator::class)->validate($plan);
        $this->assertFalse($validated['ok']);
        $this->assertTrue(collect($validated['errors'])->contains(fn ($e) => str_contains($e, 'food_not_found')));
    }

    public function test_allergy_constraint_service_blocks_tagged_food(): void
    {
        $bar = Food::query()->where('external_id', 'FDC-BRAND-DEMO-1')->firstOrFail();
        $constraints = FoodConstraintService::fromBuckets(['allergies' => ['peanut']]);
        $this->assertTrue($constraints->isAllergyViolation($bar));
        $this->assertFalse($constraints->isAllowed($bar));
    }

    public function test_invalid_zero_quantity_rejected(): void
    {
        $food = Food::query()->where('name_en', 'like', 'Rice%cooked%')->firstOrFail();
        $plan = [
            'schema_version' => '11e.1',
            'calorie_target' => 2000,
            'macro_targets' => ['protein_g' => 100, 'carbohydrates_g' => 200, 'fat_g' => 60],
            'meals_per_day' => 1,
            'days' => [[
                'day_number' => 1,
                'meals' => [[
                    'meal_number' => 1,
                    'name' => 'Meal',
                    'foods' => [[
                        'food_id' => $food->id,
                        'quantity' => 0,
                        'unit' => 'g',
                        'normalized_grams' => 0,
                        'calories_kcal' => 0,
                        'protein_g' => 0,
                        'carbohydrates_g' => 0,
                        'fat_g' => 0,
                    ]],
                    'meal_totals' => ['calories_kcal' => 0, 'protein_g' => 0, 'carbohydrates_g' => 0, 'fat_g' => 0],
                ]],
                'daily_totals' => ['calories_kcal' => 2000, 'protein_g' => 100, 'carbohydrates_g' => 200, 'fat_g' => 60],
            ]],
        ];
        $validated = app(NutritionPlanValidator::class)->validate($plan);
        $this->assertFalse($validated['ok']);
        $this->assertTrue(collect($validated['errors'])->contains(fn ($e) => str_contains($e, 'invalid_quantity')));
    }

    public function test_coach_api_returns_nutrition_plan(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'provider placeholder',
                'provider' => 'mock',
                'language' => 'en',
            ], 200),
        ]);

        $user = $this->makeUser([
            'training_goal' => 'muscle_gain',
            'weight_kg' => 80,
            'height_cm' => 180,
            'age' => 28,
            'gender' => 'male',
            'meals_per_day' => 4,
            'fitness_level' => 'intermediate',
            'training_frequency' => 4,
        ]);
        Sanctum::actingAs($user);

        $conv = $this->postJson('/api/coach/conversations', [])->assertCreated();
        $id = $conv->json('data.id');

        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'Create me a bulking meal plan.',
            'language' => 'en',
        ])->assertCreated();

        $plan = $response->json('data.nutrition_plan');
        $this->assertIsArray($plan);
        $this->assertSame('11e.1', $plan['schema_version']);
        $this->assertNull($response->json('data.training_plan'));
        $this->assertNull($response->json('data.nutrition'));
        $this->assertStringContainsString('Nutrition plan', $response->json('data.assistant_message.content'));
        $this->assertPlanFullyGrounded($plan);
    }

    public function test_context_exposes_persisted_allergies(): void
    {
        $user = $this->makeUser();
        app(NutritionPreferenceService::class)->applyMessageHints($user, 'I am allergic to peanuts');
        $ctx = app(\App\Services\UserAiContextBuilder::class)->forCoach($user);
        $this->assertContains('peanut', $ctx['nutrition']['preferences']['allergies']);
        $this->assertTrue($ctx['capabilities']['nutrition_plan_engine']);
        $this->assertTrue($ctx['capabilities']['plan_versioning']);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function assertPlanFullyGrounded(array $plan): void
    {
        foreach ($plan['days'] ?? [] as $day) {
            $this->assertArrayHasKey('daily_totals', $day);
            foreach ($day['meals'] ?? [] as $meal) {
                $this->assertArrayHasKey('meal_totals', $meal);
                foreach ($meal['foods'] ?? [] as $food) {
                    $this->assertTrue($food['grounded'] ?? false);
                    $id = (int) $food['food_id'];
                    $this->assertGreaterThan(0, $id);
                    $this->assertNotNull(Food::query()->active()->find($id));
                    $this->assertGreaterThan(0, (float) $food['calories_kcal']);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function assertPlanHasNoAllergen(array $plan, string $allergen): void
    {
        $constraints = FoodConstraintService::fromBuckets(['allergies' => [$allergen]]);
        foreach ($plan['days'] ?? [] as $day) {
            foreach ($day['meals'] ?? [] as $meal) {
                foreach ($meal['foods'] ?? [] as $food) {
                    $model = Food::query()->findOrFail((int) $food['food_id']);
                    $this->assertFalse($constraints->isAllergyViolation($model), 'Found allergen food: '.$model->name_en);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function assertPlanHasNoNameContains(array $plan, string $needle): void
    {
        $needle = mb_strtolower($needle);
        foreach ($plan['days'] ?? [] as $day) {
            foreach ($day['meals'] ?? [] as $meal) {
                foreach ($meal['foods'] ?? [] as $food) {
                    $this->assertFalse(
                        str_contains(mb_strtolower((string) ($food['name'] ?? '')), $needle),
                        'Unexpected food: '.($food['name'] ?? ''),
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function planContainsName(array $plan, string $needle): bool
    {
        $needle = mb_strtolower($needle);
        foreach ($plan['days'] ?? [] as $day) {
            foreach ($day['meals'] ?? [] as $meal) {
                foreach ($meal['foods'] ?? [] as $food) {
                    if (str_contains(mb_strtolower((string) ($food['name'] ?? '')), $needle)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sampleContext(array $overrides = []): array
    {
        return array_replace_recursive([
            'available' => true,
            'body' => ['sex' => 'male', 'age' => 28, 'height_cm' => 180, 'weight_kg' => 80],
            'goals' => ['primary_goal' => 'muscle_gain'],
            'training' => [
                'fitness_level' => 'intermediate',
                'training_frequency_per_week' => 4,
                'activity_level' => 'moderately_active',
            ],
            'nutrition' => ['meals_per_day' => 4],
            'weight_kg' => 80,
            'height_cm' => 180,
            'age' => 28,
            'gender' => 'male',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function makeUser(array $profile = []): User
    {
        $user = User::factory()->create(['role_id' => 2]);
        Profile::query()->create(array_merge([
            'user_id' => $user->id,
            'age' => 25,
            'gender' => 'male',
            'height_cm' => 175,
            'weight_kg' => 75,
            'locale' => 'en',
            'fitness_level' => 'beginner',
            'training_goal' => 'general_fitness',
            'training_frequency' => 3,
            'meals_per_day' => 4,
            'profile_completed_at' => now(),
        ], $profile));

        return $user->fresh(['profile']);
    }
}
