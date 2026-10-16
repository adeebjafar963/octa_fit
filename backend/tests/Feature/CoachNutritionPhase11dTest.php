<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\Nutrition\FoodRetrievalService;
use App\Services\Coach\Nutrition\NutritionCalculator;
use App\Services\Coach\Nutrition\NutritionLookupService;
use App\Services\Coach\Nutrition\ServingNormalizer;
use Database\Seeders\NutritionFoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachNutritionPhase11dTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->insert([
            ['id' => 1, 'name' => 'admin', 'display_name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'user', 'display_name' => 'User', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->seed(NutritionFoodSeeder::class);
    }

    public function test_search_existing_food(): void
    {
        $result = app(FoodRetrievalService::class)->search('cooked rice', [
            'preparation' => 'cooked',
            'prefer_generic' => true,
        ]);
        $this->assertContains($result['status'], ['exact_match', 'strong_match']);
        $this->assertNotEmpty($result['foods']);
        $this->assertTrue($result['foods'][0]['grounded']);
        $this->assertSame('cooked', $result['foods'][0]['preparation']);
    }

    public function test_retrieve_by_id_and_external_id(): void
    {
        $food = Food::query()->where('external_id', 'FDC-168878')->first();
        $this->assertNotNull($food);
        $this->assertNotNull(app(FoodRetrievalService::class)->findById($food->id));
        $this->assertNotNull(app(FoodRetrievalService::class)->findByExternalId(
            NutritionFoodSeeder::SOURCE,
            'FDC-168878',
        ));
    }

    public function test_food_not_found(): void
    {
        $result = app(FoodRetrievalService::class)->search('xyznonexistentfood999');
        $this->assertSame('not_found', $result['status']);
    }

    public function test_raw_vs_cooked_rice_remain_distinct(): void
    {
        $cooked = app(FoodRetrievalService::class)->search('rice cooked', ['preparation' => 'cooked']);
        $raw = app(FoodRetrievalService::class)->search('rice raw', ['preparation' => 'raw']);
        $this->assertSame('cooked', $cooked['foods'][0]['preparation']);
        $this->assertSame('raw', $raw['foods'][0]['preparation']);
        $this->assertNotEquals($cooked['foods'][0]['food_id'], $raw['foods'][0]['food_id']);
        $this->assertNotEquals(
            $cooked['foods'][0]['per_100g']['calories_kcal'],
            $raw['foods'][0]['per_100g']['calories_kcal'],
        );
    }

    public function test_calculate_100g_and_200g_from_fixture(): void
    {
        $food = Food::query()->where('external_id', 'FDC-168878')->firstOrFail();
        $calc = app(NutritionCalculator::class);
        $one = $calc->calculateForFood($food, 100, 'g');
        $two = $calc->calculateForFood($food, 200, 'g');

        $this->assertSame(round($food->calories_kcal_per_100g, 2), $one['calories_kcal']);
        $this->assertSame(round($food->calories_kcal_per_100g * 2, 2), $two['calories_kcal']);
        $this->assertSame(round($food->protein_g_per_100g * 2, 2), $two['protein_g']);
        $this->assertSame(round($food->carbohydrates_g_per_100g * 2, 2), $two['carbohydrates_g']);
        $this->assertSame(round($food->fat_g_per_100g * 2, 2), $two['fat_g']);
    }

    public function test_calculate_kg_quantity(): void
    {
        $food = Food::query()->where('external_id', 'FDC-171477')->firstOrFail();
        $row = app(NutritionCalculator::class)->calculateForFood($food, 0.2, 'kg');
        $this->assertSame(200.0, $row['normalized_grams']);
        $this->assertSame(round($food->calories_kcal_per_100g * 2, 2), $row['calories_kcal']);
    }

    public function test_piece_calculation_uses_verified_weight(): void
    {
        $food = Food::query()->where('external_id', 'FDC-173424')->firstOrFail();
        $this->assertSame(50.0, (float) $food->piece_grams);
        $row = app(NutritionCalculator::class)->calculateForFood($food, 2, 'piece');
        $this->assertSame(100.0, $row['normalized_grams']);
        $this->assertSame(round($food->calories_kcal_per_100g, 2), $row['calories_kcal']);
    }

    public function test_piece_without_weight_rejected(): void
    {
        $food = Food::query()->where('external_id', 'FDC-168878')->firstOrFail();
        $this->expectException(InvalidArgumentException::class);
        app(NutritionCalculator::class)->calculateForFood($food, 1, 'piece');
    }

    public function test_reject_zero_and_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ServingNormalizer::class)->normalize(0, 'g');
    }

    public function test_reject_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ServingNormalizer::class)->normalize(-10, 'g');
    }

    public function test_reject_invalid_unit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ServingNormalizer::class)->normalize(100, 'stones');
    }

    public function test_reject_volume_without_density(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ServingNormalizer::class)->normalize(250, 'ml');
    }

    public function test_meal_sum_macros(): void
    {
        $rice = Food::query()->where('external_id', 'FDC-168878')->firstOrFail();
        $chicken = Food::query()->where('external_id', 'FDC-171477')->firstOrFail();
        $meal = app(NutritionCalculator::class)->calculateMeal([
            ['food_id' => $rice->id, 'quantity' => 150, 'unit' => 'g'],
            ['food_id' => $chicken->id, 'quantity' => 200, 'unit' => 'g'],
        ]);
        $this->assertTrue($meal['ok']);
        $expectedCalories = round($rice->calories_kcal_per_100g * 1.5 + $chicken->calories_kcal_per_100g * 2, 2);
        $expectedProtein = round($rice->protein_g_per_100g * 1.5 + $chicken->protein_g_per_100g * 2, 2);
        $this->assertSame($expectedCalories, $meal['totals']['calories_kcal']);
        $this->assertSame($expectedProtein, $meal['totals']['protein_g']);
    }

    public function test_fake_food_id_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(NutritionCalculator::class)->calculateForFoodId(999999, 100, 'g');
    }

    public function test_inactive_food_rejected(): void
    {
        $food = Food::query()->where('external_id', 'FDC-168878')->firstOrFail();
        $food->update(['is_active' => false]);
        $this->expectException(InvalidArgumentException::class);
        app(NutritionCalculator::class)->calculateForFoodId($food->id, 100, 'g');
    }

    public function test_branded_not_silently_used_for_generic_query(): void
    {
        $result = app(FoodRetrievalService::class)->search('chicken breast', [
            'prefer_generic' => true,
        ]);
        $this->assertNotEmpty($result['foods']);
        $this->assertTrue($result['foods'][0]['is_generic']);
    }

    public function test_import_idempotent(): void
    {
        $before = Food::query()->count();
        $this->seed(NutritionFoodSeeder::class);
        $this->assertSame($before, Food::query()->count());
    }

    public function test_lookup_200g_chicken_message(): void
    {
        $food = Food::query()->where('external_id', 'FDC-171477')->firstOrFail();
        $result = app(NutritionLookupService::class)->handleMessage(
            'How many calories and protein are in 200g chicken breast cooked roasted?',
            'en',
        );
        $this->assertNotNull($result);
        $this->assertTrue($result['ok']);
        $this->assertSame(round($food->calories_kcal_per_100g * 2, 2), $result['totals']['calories_kcal']);
        $this->assertSame(round($food->protein_g_per_100g * 2, 2), $result['totals']['protein_g']);
        $this->assertSame($food->source, $result['foods'][0]['source']);
    }

    public function test_lookup_unknown_food(): void
    {
        $result = app(NutritionLookupService::class)->handleMessage(
            'How many calories are in 100g unicorn meat?',
            'en',
        );
        $this->assertNotNull($result);
        $this->assertFalse($result['ok']);
        $this->assertNull($result['totals']);
    }

    public function test_coach_api_returns_nutrition_for_calorie_question(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'ignored provider text',
                'provider' => 'mock',
                'language' => 'en',
            ], 200),
        ]);

        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations')->json('data.id');
        $food = Food::query()->where('external_id', 'FDC-168878')->firstOrFail();

        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'How many calories are in 100g cooked rice?',
            'language' => 'en',
        ]);

        $response->assertCreated();
        $nutrition = $response->json('data.nutrition');
        $this->assertIsArray($nutrition);
        $this->assertTrue($nutrition['ok']);
        $this->assertEqualsWithDelta(round($food->calories_kcal_per_100g, 2), (float) $nutrition['totals']['calories_kcal'], 0.01);
        $this->assertStringContainsString((string) (int) round($food->calories_kcal_per_100g), $response->json('data.assistant_message.content'));
        $this->assertNull($response->json('data.training_plan'));
    }

    public function test_missing_nutrient_stays_null(): void
    {
        $food = Food::query()->where('external_id', 'FDC-169760')->firstOrFail();
        $this->assertNull($food->sugar_g_per_100g);
        $row = app(NutritionCalculator::class)->calculateForFood($food, 100, 'g');
        $this->assertNull($row['sugar_g']);
    }

    private function makeUser(string $email = 'nutrition11d@test.com'): User
    {
        $user = User::query()->create([
            'name' => 'Nutrition',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 2,
            'is_active' => true,
        ]);
        Profile::query()->create([
            'user_id' => $user->id,
            'locale' => 'en',
            'height_cm' => 180,
            'weight_kg' => 80,
            'fitness_level' => 'intermediate',
            'training_goal' => 'muscle_gain',
            'training_experience' => '1_3_years',
            'injury_status' => 'none',
            'profile_completed_at' => now(),
        ]);

        return $user->fresh(['profile']);
    }
}
