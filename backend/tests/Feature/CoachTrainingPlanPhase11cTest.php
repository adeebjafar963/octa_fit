<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\TrainingIntentExtractor;
use App\Services\Coach\TrainingPlanEngine;
use App\Services\Coach\TrainingPlanValidator;
use Database\Seeders\TrainingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachTrainingPlanPhase11cTest extends TestCase
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
    }

    public function test_simple_question_does_not_trigger_plan(): void
    {
        $extractor = app(TrainingIntentExtractor::class);
        $this->assertFalse($extractor->isPlanRequest('How many sets should I do?'));
        $this->assertTrue($extractor->isPlanRequest('Create me a 4-day bulking workout program.'));
    }

    public function test_hypertrophy_bulking_4_day_plan_uses_real_ids(): void
    {
        $engine = app(TrainingPlanEngine::class);
        $result = $engine->generateFromMessage(
            'Create me a 4-day bulking workout program.',
            $this->sampleContext([
                'primary_goal' => 'muscle_gain',
                'fitness_level' => 'intermediate',
                'training_frequency_per_week' => 4,
                'training_location' => 'gym',
            ]),
            'en',
        );

        $this->assertSame([], $result['errors']);
        $plan = $result['plan'];
        $this->assertNotNull($plan);
        $this->assertSame('11c.1', $plan['schema_version']);
        $this->assertSame('hypertrophy', $plan['goal']);
        $this->assertSame(4, $plan['days_per_week']);
        $this->assertSame(4, $plan['requested_days_per_week']);
        $this->assertFalse($plan['nutrition_included']);
        $this->assertCount(4, $plan['sessions']);
        $this->assertPlanFullyGrounded($plan);
    }

    public function test_cutting_plan_goal_is_fat_loss(): void
    {
        $plan = $this->generate('Give me a 3-day cutting program for intermediate.', [
            'fitness_level' => 'intermediate',
        ]);
        $this->assertSame('fat_loss', $plan['goal']);
        $this->assertSame(3, $plan['days_per_week']);
        $this->assertPlanFullyGrounded($plan);
    }

    public function test_strength_plan(): void
    {
        $plan = $this->generate('Build a 4-day strength program.', [
            'fitness_level' => 'advanced',
        ]);
        $this->assertSame('strength', $plan['goal']);
        $this->assertPlanFullyGrounded($plan);
        $first = $plan['sessions'][0]['exercises'][0];
        $this->assertLessThanOrEqual(6, (int) $first['reps_max']);
    }

    public function test_beginner_intermediate_advanced_adapt_volume(): void
    {
        $beg = $this->generate('Create a 3-day beginner hypertrophy plan.', ['fitness_level' => 'beginner']);
        $adv = $this->generate('Create a 3-day advanced hypertrophy plan.', ['fitness_level' => 'advanced']);
        $begSets = (int) $beg['sessions'][0]['exercises'][0]['sets'];
        $advSets = (int) $adv['sessions'][0]['exercises'][0]['sets'];
        $this->assertGreaterThanOrEqual($begSets, $advSets); // advanced >= beginner volume on first slot
        $this->assertSame('beginner', $beg['level']);
        $this->assertSame('advanced', $adv['level']);
    }

    public function test_home_workout_prefers_home_friendly_discipline(): void
    {
        $plan = $this->generate('Give me a 3-day home workout program.', [
            'training_location' => 'home',
            'fitness_level' => 'beginner',
        ]);
        $this->assertSame('home_calisthenics', $plan['discipline']);
        $this->assertPlanFullyGrounded($plan);
    }

    public function test_mma_plan_uses_mma_discipline(): void
    {
        $plan = $this->generate('Create a 3-day MMA conditioning program.', [
            'primary_goal' => 'mma',
            'fitness_level' => 'intermediate',
        ]);
        $this->assertSame('mma', $plan['discipline']);
        $this->assertContains($plan['goal'], ['conditioning', 'hypertrophy']);
        $this->assertPlanFullyGrounded($plan);
        foreach ($plan['sessions'] as $session) {
            foreach ($session['exercises'] as $ex) {
                $model = Exercise::query()->with('category')->find($ex['exercise_id']);
                $this->assertNotNull($model);
                $this->assertSame('mma', $model->category?->type);
            }
        }
    }

    public function test_days_2_through_6_supported(): void
    {
        foreach ([2, 3, 4, 5, 6] as $days) {
            $plan = $this->generate("Make a {$days}-day hypertrophy program.", [
                'fitness_level' => 'intermediate',
            ]);
            $this->assertSame($days, $plan['requested_days_per_week']);
            $this->assertSame($days, $plan['days_per_week']);
            $this->assertCount($days, $plan['sessions']);
            $this->assertPlanFullyGrounded($plan);
        }
    }

    public function test_validator_rejects_fake_exercise_id(): void
    {
        $plan = $this->generate('Create me a 4-day bulking workout program.', [
            'fitness_level' => 'intermediate',
        ]);
        $validator = app(TrainingPlanValidator::class);
        $result = $validator->validateWithInjectedIds($plan, [999999]);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validator_rejects_inactive_exercise(): void
    {
        $plan = $this->generate('Create me a 4-day bulking workout program.', [
            'fitness_level' => 'intermediate',
        ]);
        $id = $plan['sessions'][0]['exercises'][0]['exercise_id'];
        Exercise::query()->where('id', $id)->update(['is_active' => false]);

        $validator = app(TrainingPlanValidator::class);
        $result = $validator->validate($plan);
        $this->assertFalse($result['ok']);
    }

    public function test_validator_rejects_bad_sets(): void
    {
        $plan = $this->generate('Create me a 3-day hypertrophy plan.', [
            'fitness_level' => 'beginner',
        ]);
        $plan['sessions'][0]['exercises'][0]['sets'] = 0;
        $result = app(TrainingPlanValidator::class)->validate($plan);
        $this->assertFalse($result['ok']);
    }

    public function test_user_context_influences_level_and_frequency_defaults(): void
    {
        $intent = app(TrainingIntentExtractor::class)->extract(
            'Give me a hypertrophy training plan.',
            $this->sampleContext([
                'fitness_level' => 'advanced',
                'training_frequency_per_week' => 5,
            ]),
        );
        $this->assertTrue($intent['is_plan_request']);
        $this->assertSame('advanced', $intent['level']);
        $this->assertSame(5, $intent['days_per_week']);
        $this->assertTrue($intent['source']['from_profile_frequency']);
    }

    public function test_coach_api_returns_training_plan_for_bulking_request(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'Here is your plan overview.',
                'provider' => 'mock',
                'language' => 'en',
                'grounded_exercises' => [],
            ], 200),
        ]);

        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations')->json('data.id');
        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'Create me a 4-day bulking workout program.',
            'language' => 'en',
        ]);

        $response->assertCreated();
        $plan = $response->json('data.training_plan');
        $this->assertIsArray($plan);
        $this->assertSame('hypertrophy', $plan['goal']);
        $this->assertSame(4, $plan['days_per_week']);
        $this->assertPlanFullyGrounded($plan);
        $this->assertStringContainsString('Day 1', $response->json('data.assistant_message.content'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function generate(string $message, array $overrides = []): array
    {
        $result = app(TrainingPlanEngine::class)->generateFromMessage(
            $message,
            $this->sampleContext($overrides),
            'en',
        );
        $this->assertSame([], $result['errors'], 'errors: '.json_encode($result['errors']));
        $this->assertNotNull($result['plan']);

        return $result['plan'];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function assertPlanFullyGrounded(array $plan): void
    {
        $this->assertNotEmpty($plan['sessions']);
        $seenGlobal = [];
        foreach ($plan['sessions'] as $session) {
            $this->assertNotEmpty($session['exercises']);
            $seenSession = [];
            foreach ($session['exercises'] as $ex) {
                $this->assertTrue($ex['grounded']);
                $this->assertIsInt($ex['exercise_id']);
                $this->assertGreaterThan(0, $ex['exercise_id']);
                $this->assertGreaterThan(0, (int) $ex['sets']);
                $model = Exercise::query()->active()->find($ex['exercise_id']);
                $this->assertNotNull($model, 'missing exercise '.$ex['exercise_id']);
                $this->assertArrayNotHasKey($ex['exercise_id'], $seenSession, 'duplicate in session');
                $seenSession[$ex['exercise_id']] = true;
                $seenGlobal[$ex['exercise_id']] = true;
                $this->assertSame('/training/exercises/'.$ex['exercise_id'], $ex['flutter_route']);
            }
        }
        $this->assertNotEmpty($seenGlobal);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sampleContext(array $overrides = []): array
    {
        return array_merge([
            'available' => true,
            'schema_version' => '11a.1',
            'locale' => 'en',
            'primary_goal' => 'muscle_gain',
            'goals' => ['primary_goal' => $overrides['primary_goal'] ?? 'muscle_gain', 'secondary_goals' => []],
            'fitness_level' => 'intermediate',
            'training' => [
                'fitness_level' => $overrides['fitness_level'] ?? 'intermediate',
                'training_frequency_per_week' => $overrides['training_frequency_per_week'] ?? 4,
                'training_location' => $overrides['training_location'] ?? 'gym',
                'equipment' => $overrides['equipment'] ?? [],
                'session_duration_minutes' => 60,
            ],
            'training_location' => $overrides['training_location'] ?? 'gym',
            'training_frequency_per_week' => $overrides['training_frequency_per_week'] ?? 4,
            'recovery' => ['injury_status' => 'none', 'injury_areas' => []],
            'workout' => ['recent_training_types' => [], 'recent_workouts' => []],
        ], $overrides);
    }

    private function makeUser(string $email = 'plan11c@test.com'): User
    {
        $user = User::query()->create([
            'name' => 'Plan 11C',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 2,
            'is_active' => true,
        ]);
        Profile::query()->create([
            'user_id' => $user->id,
            'locale' => 'en',
            'height_cm' => 180,
            'weight_kg' => 82,
            'fitness_level' => 'intermediate',
            'training_goal' => 'muscle_gain',
            'training_experience' => '1_3_years',
            'training_frequency' => 4,
            'training_location' => 'gym',
            'injury_status' => 'none',
            'profile_completed_at' => now(),
        ]);

        return $user->fresh(['profile']);
    }
}
