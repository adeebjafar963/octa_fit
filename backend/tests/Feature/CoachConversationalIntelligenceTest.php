<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachRequestUnderstanding;
use App\Services\Coach\Nutrition\NutritionLookupService;
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
 * Conversational intelligence overhaul — Arabic NL, gym semantics, direct food answers.
 */
class CoachConversationalIntelligenceTest extends TestCase
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

    public function test_arabic_apple_is_direct_food_lookup(): void
    {
        $routing = app(CoachRequestUnderstanding::class)->understand(
            'التفاحة الواحدة كم سعرة حرارية؟',
            $this->homeContext(),
        );
        $this->assertSame('food_lookup', $routing['intent']);
        $this->assertSame('direct', $routing['response_mode']);
        $this->assertTrue($routing['skip_profile']);
        $this->assertFalse($routing['is_plan_request']);

        $result = app(NutritionLookupService::class)->handleMessage(
            'التفاحة الواحدة كم سعرة حرارية؟',
            'ar',
        );
        $this->assertNotNull($result);
        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['totals']['calories_kcal']);
        $msg = app(NutritionLookupService::class)->formatMessage($result, 'ar', 'direct');
        $this->assertStringContainsString('سعرة', $msg);
        $this->assertStringNotContainsString('بناءً على ملفك', $msg);
        $this->assertStringNotContainsString('OCTA FIT database', $msg);
        $this->assertStringNotContainsString('Food not found', $msg);
    }

    public function test_arabic_chicken_macros_lookup(): void
    {
        $result = app(NutritionLookupService::class)->handleMessage(
            '200 غرام صدر دجاج كم سعرة وبروتين فيهم؟',
            'ar',
        );
        $this->assertNotNull($result);
        $this->assertTrue($result['ok']);
        $this->assertGreaterThan(0, (float) $result['totals']['calories_kcal']);
        $this->assertGreaterThan(0, (float) $result['totals']['protein_g']);
        $msg = app(NutritionLookupService::class)->formatMessage($result, 'ar', 'direct');
        $this->assertStringContainsString('بروتين', $msg);
        $this->assertStringNotContainsString('تمرين', $msg);
    }

    public function test_arabic_gym_program_is_gym_not_home(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract(
            'بدي برنامج للصالة الرياضية للجيم.',
            $this->homeContext(),
        );
        $this->assertTrue($intent['is_plan_request']);
        $this->assertSame('bodybuilding', $intent['discipline']);
        $this->assertSame('gym', $intent['equipment_mode']);
        $this->assertTrue($intent['discipline_explicit']);
        $this->assertNotSame('home_calisthenics', $intent['discipline']);
        $this->assertNotSame('general_fitness', $intent['goal']);
    }

    public function test_profile_home_arabic_gym_overrides(): void
    {
        $plan = app(TrainingPlanEngine::class)->generateFromMessage(
            'بدي برنامج للنادي الرياضي.',
            $this->homeContext(),
        )['plan'];
        $this->assertNotNull($plan);
        $this->assertSame('bodybuilding', $plan['discipline']);
        $this->assertSame('gym', app(TrainingIntentExtractor::class)->extract(
            'بدي برنامج للنادي الرياضي.',
            $this->homeContext(),
        )['equipment_mode']);
    }

    public function test_arabic_mma_four_days_two_hours(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract(
            'اعطيني خطة فنون قتالية مختلطة من أربع أيام وساعتين كل يوم.',
            $this->homeContext(),
        );
        $this->assertSame('mma', $intent['discipline']);
        $this->assertSame(4, $intent['days_per_week']);
        $this->assertSame(120, $intent['session_duration_minutes']);
    }

    public function test_bodybuilding_gym_five_days(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract(
            'بدي برنامج تضخيم بالجيم 5 أيام.',
            $this->homeContext(),
        );
        $this->assertSame('bodybuilding', $intent['discipline']);
        $this->assertSame('gym', $intent['equipment_mode']);
        $this->assertSame(5, $intent['days_per_week']);
        $this->assertSame('hypertrophy', $intent['goal']);
    }

    public function test_general_sports_question_not_a_plan(): void
    {
        $routing = app(CoachRequestUnderstanding::class)->understand(
            'شو هو progressive overload؟',
            $this->homeContext(),
        );
        $this->assertSame('general_sports_question', $routing['intent']);
        $this->assertFalse($routing['is_plan_request']);
        $this->assertTrue($routing['skip_profile']);
    }

    public function test_coach_api_arabic_apple_no_profile_no_training(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'SHOULD_NOT_APPEAR_IF_DIRECT',
                'provider' => 'mock',
                'language' => 'ar',
            ], 200),
        ]);

        $user = $this->makeUser('conv-apple@test.com');
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations')->json('data.id');
        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'التفاحة الواحدة كم سعرة حرارية؟',
            'language' => 'ar',
        ]);
        $response->assertCreated();
        $content = (string) $response->json('data.assistant_message.content');
        $this->assertStringContainsString('سعرة', $content);
        $this->assertStringNotContainsString('بناءً على ملفك', $content);
        $this->assertStringNotContainsString('SHOULD_NOT_APPEAR', $content);
        $this->assertNull($response->json('data.training_plan'));
        $this->assertTrue($response->json('data.nutrition.ok'));
    }

    public function test_coach_api_arabic_gym_plan(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'إليك برنامج الجيم.',
                'provider' => 'mock',
                'language' => 'ar',
            ], 200),
        ]);

        $user = $this->makeUser('conv-gym@test.com', ['training_location' => 'home']);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations')->json('data.id');
        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'بدي برنامج للصالة الرياضية للجيم.',
            'language' => 'ar',
        ]);
        $response->assertCreated();
        $plan = $response->json('data.training_plan');
        $this->assertIsArray($plan);
        $this->assertSame('bodybuilding', $plan['discipline']);
        $this->assertNotSame('home_calisthenics', $plan['discipline']);
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
                'session_duration_minutes' => 60,
            ],
            'primary_goal' => 'general_fitness',
            'training_location' => 'home',
            'memory_context' => [
                'count' => 1,
                'memories' => [
                    ['category' => 'equipment', 'key' => 'home_only', 'value' => ['mode' => 'home']],
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
            'name' => 'Conv Tester',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 2,
            'is_active' => true,
        ]);
        Profile::query()->create(array_merge([
            'user_id' => $user->id,
            'locale' => 'ar',
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
