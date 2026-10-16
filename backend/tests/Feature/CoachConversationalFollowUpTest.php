<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachFollowUpInterpreter;
use App\Services\Coach\CoachPlanContextStore;
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

class CoachConversationalFollowUpTest extends TestCase
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

    public function test_1_intensify_day_3_only_changes_day_3(): void
    {
        $engine = app(TrainingPlanEngine::class);
        $first = $engine->generateFromMessage(
            'بدي خطة MMA من 4 أيام، كل يوم ساعتين.',
            $this->homeContext(),
            'ar',
        );
        $this->assertNotNull($first['plan']);
        $this->assertSame('mma', $first['plan']['discipline']);
        $this->assertSame(4, $first['plan']['days_per_week']);
        $this->assertSame(120, $first['plan']['requested_session_duration_minutes']);

        $follow = app(CoachFollowUpInterpreter::class)->interpret(
            'خلي اليوم الثالث أصعب.',
            ['plan' => $first['plan'], 'intent' => $first['intent']],
        );
        $this->assertTrue($follow['is_follow_up']);
        $this->assertSame('intensify_day', $follow['action']);
        $this->assertSame(3, $follow['day']);

        $updated = $engine->intensifyDay($first['plan'], 3);
        $this->assertSame('mma', $updated['discipline']);
        $this->assertSame(4, $updated['days_per_week']);
        $this->assertSame(120, $updated['requested_session_duration_minutes']);

        // Days 1,2,4 unchanged; day 3 harder.
        foreach ([0, 1, 3] as $idx) {
            $this->assertSame(
                $first['plan']['sessions'][$idx]['exercises'][0]['sets'] ?? null,
                $updated['sessions'][$idx]['exercises'][0]['sets'] ?? null,
            );
            $this->assertSame(
                $first['plan']['sessions'][$idx]['exercises'][0]['exercise_id'] ?? null,
                $updated['sessions'][$idx]['exercises'][0]['exercise_id'] ?? null,
            );
        }
        $beforeSets = (int) ($first['plan']['sessions'][2]['exercises'][0]['sets'] ?? 0);
        $afterSets = (int) ($updated['sessions'][2]['exercises'][0]['sets'] ?? 0);
        $this->assertSame($beforeSets + 1, $afterSets);
        $this->assertStringContainsString('harder', mb_strtolower($updated['sessions'][2]['title'] ?? ''));
    }

    public function test_2_replace_last_exercise_day_2(): void
    {
        $engine = app(TrainingPlanEngine::class);
        $first = $engine->generateFromMessage(
            'بدي خطة MMA من 4 أيام.',
            $this->homeContext(),
            'ar',
        );
        $this->assertNotNull($first['plan']);

        $follow = app(CoachFollowUpInterpreter::class)->interpret(
            'بدل آخر تمرين باليوم الثاني.',
            ['plan' => $first['plan'], 'intent' => $first['intent']],
        );
        $this->assertTrue($follow['is_follow_up']);
        $this->assertSame('replace_last_exercise', $follow['action']);
        $this->assertSame(2, $follow['day']);

        $day2Before = $first['plan']['sessions'][1]['exercises'];
        $lastBefore = $day2Before[count($day2Before) - 1]['exercise_id'];
        $otherBefore = array_column(array_slice($day2Before, 0, -1), 'exercise_id');

        $replaced = $engine->replaceLastExerciseOnDay($first['plan'], 2, 'ar');
        $this->assertNotNull($replaced['plan']);
        $this->assertSame([], $replaced['errors']);
        $day2After = $replaced['plan']['sessions'][1]['exercises'];
        $lastAfter = $day2After[count($day2After) - 1]['exercise_id'];
        $this->assertNotSame($lastBefore, $lastAfter);
        $this->assertSame($otherBefore, array_column(array_slice($day2After, 0, -1), 'exercise_id'));

        // Other days unchanged.
        $this->assertSame(
            array_column($first['plan']['sessions'][0]['exercises'], 'exercise_id'),
            array_column($replaced['plan']['sessions'][0]['exercises'], 'exercise_id'),
        );
        $this->assertSame('mma', $replaced['plan']['discipline']);
        $this->assertSame(4, $replaced['plan']['days_per_week']);
    }

    public function test_3_progressive_overload_natural_answer_via_api(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => "ملخص قائم على مصادر مسترجعة (المحاكي لا يخترع دراسات):\n• [A] Fake Source: chunk",
                'provider' => 'mock',
                'language' => 'ar',
            ], 200),
        ]);

        $user = $this->makeUser('fu-po@test.com');
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations')->json('data.id');
        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'شو هو progressive overload؟',
            'language' => 'ar',
        ]);
        $response->assertCreated();
        $content = (string) $response->json('data.assistant_message.content');
        $this->assertStringContainsString('Progressive overload', $content);
        $this->assertStringNotContainsString('مصادر مسترجعة', $content);
        $this->assertStringNotContainsString('ملخص قائم على مصادر', $content);
        $this->assertStringNotContainsString('بناءً على ملفك', $content);
        $this->assertNull($response->json('data.training_plan'));
        $routing = app(CoachRequestUnderstanding::class)->understand('شو هو progressive overload؟', $this->homeContext());
        $this->assertSame('general_sports_question', $routing['intent']);
    }

    public function test_4_and_5_nutrition_direct(): void
    {
        $apple = app(NutritionLookupService::class)->handleMessage('التفاحة كم سعرة؟', 'ar');
        $this->assertTrue($apple['ok'] ?? false);
        $chicken = app(NutritionLookupService::class)->handleMessage('200 غرام صدر دجاج كم بروتين؟', 'ar');
        $this->assertTrue($chicken['ok'] ?? false);
        $this->assertGreaterThan(0, (float) ($chicken['totals']['protein_g'] ?? 0));
    }

    public function test_6_home_profile_gym_request(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract('بدي برنامج للجيم.', $this->homeContext());
        $this->assertSame('gym', $intent['equipment_mode']);
        $this->assertSame('bodybuilding', $intent['discipline']);
    }

    public function test_7_mma_overrides_general_fitness_profile(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract('بدي برنامج MMA 4 أيام.', $this->homeContext());
        $this->assertSame('mma', $intent['discipline']);
        $this->assertSame(4, $intent['days_per_week']);
    }

    public function test_8_two_hours_overrides_60_profile(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract('بدي برنامج ساعتين.', $this->homeContext());
        $this->assertSame(120, $intent['session_duration_minutes']);
        $this->assertTrue($intent['duration_explicit']);
    }

    public function test_api_follow_up_intensify_uses_active_plan(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'تم تحديث اليوم الثالث.',
                'provider' => 'mock',
                'language' => 'ar',
            ], 200),
        ]);

        $user = $this->makeUser('fu-api@test.com');
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations')->json('data.id');

        $first = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'بدي خطة MMA من 4 أيام، كل يوم ساعتين.',
            'language' => 'ar',
        ]);
        $first->assertCreated();
        $plan1 = $first->json('data.training_plan');
        $this->assertSame('mma', $plan1['discipline']);
        $this->assertSame(4, $plan1['days_per_week']);

        $second = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'خلي اليوم الثالث أصعب.',
            'language' => 'ar',
        ]);
        $second->assertCreated();
        $plan2 = $second->json('data.training_plan');
        $this->assertIsArray($plan2);
        $this->assertSame('mma', $plan2['discipline']);
        $this->assertSame(4, $plan2['days_per_week']);
        $this->assertStringContainsString('harder', mb_strtolower($plan2['sessions'][2]['title'] ?? ''));
        $content = (string) $second->json('data.assistant_message.content');
        $this->assertStringNotContainsString('أنا مدرب أوكتا فيت', $content);
        $this->assertStringNotContainsString('Ask something specific', $content);
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
            'session_duration_minutes' => 60,
        ];
    }

    private function makeUser(string $email): User
    {
        $user = User::query()->create([
            'name' => 'FU Tester',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 2,
            'is_active' => true,
        ]);
        Profile::query()->create([
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
        ]);

        return $user->fresh(['profile']);
    }
}
