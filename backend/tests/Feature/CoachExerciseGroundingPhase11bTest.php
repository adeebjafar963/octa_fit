<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\CoachExerciseRetrievalService;
use Database\Seeders\TrainingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachExerciseGroundingPhase11bTest extends TestCase
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

    public function test_retrieve_chest_returns_real_active_ids(): void
    {
        $service = app(CoachExerciseRetrievalService::class);
        $results = $service->retrieveForMessage('Give me a chest workout', [
            'available' => true,
            'locale' => 'en',
            'fitness_level' => 'intermediate',
        ], 'en');

        $this->assertNotEmpty($results);
        foreach ($results as $row) {
            $this->assertTrue($row['grounded']);
            $this->assertIsInt($row['exercise_id']);
            $this->assertGreaterThan(0, $row['exercise_id']);
            $this->assertTrue($row['is_active']);
            $this->assertSame('/training/exercises/'.$row['exercise_id'], $row['flutter_route']);
            $this->assertNotNull(Exercise::query()->active()->find($row['exercise_id']));
        }
    }

    public function test_filter_by_discipline_bodybuilding(): void
    {
        $service = app(CoachExerciseRetrievalService::class);
        $results = $service->retrieveForMessage('bodybuilding hypertrophy chest', [
            'available' => true,
        ], 'en');

        $this->assertNotEmpty($results);
        foreach ($results as $row) {
            $this->assertSame('bodybuilding', $row['discipline']);
        }
    }

    public function test_inactive_exercise_fails_validation(): void
    {
        $exercise = Exercise::query()->where('slug', 'like', 'bb-chest%')->first();
        $this->assertNotNull($exercise);
        $exercise->update(['is_active' => false]);

        $service = app(CoachExerciseRetrievalService::class);
        $validated = $service->validateIds([$exercise->id], 'en');

        $this->assertSame([], $validated['valid']);
        $this->assertCount(1, $validated['rejected']);
        $this->assertSame('not_found_or_inactive', $validated['rejected'][0]['reason']);
    }

    public function test_nonexistent_id_is_rejected(): void
    {
        $service = app(CoachExerciseRetrievalService::class);
        $validated = $service->validateIds([999999], 'en');

        $this->assertSame([], $validated['valid']);
        $this->assertSame(999999, $validated['rejected'][0]['id']);
        $this->assertSame('not_found_or_inactive', $validated['rejected'][0]['reason']);
    }

    public function test_duplicate_ids_are_deduplicated(): void
    {
        $exercise = Exercise::query()->active()->first();
        $this->assertNotNull($exercise);

        $service = app(CoachExerciseRetrievalService::class);
        $validated = $service->validateIds([$exercise->id, $exercise->id, (string) $exercise->id], 'en');

        $this->assertCount(1, $validated['valid']);
        $this->assertSame($exercise->id, $validated['valid'][0]['exercise_id']);
    }

    public function test_arabic_localization_uses_name_ar_when_present(): void
    {
        $exercise = Exercise::query()->active()->whereNotNull('name_ar')->first();
        $this->assertNotNull($exercise);

        $service = app(CoachExerciseRetrievalService::class);
        $grounded = $service->toGrounded($exercise, 'ar');

        $this->assertSame($exercise->name_ar ?: $exercise->name_en, $grounded['name']);
        $this->assertSame($exercise->name_en, $grounded['name_en']);
        $this->assertSame($exercise->name_ar, $grounded['name_ar']);
    }

    public function test_user_context_home_location_biases_home_friendly(): void
    {
        $service = app(CoachExerciseRetrievalService::class);
        $filters = $service->buildFilters('Give me a workout', [
            'available' => true,
            'training_location' => 'home',
            'training' => ['training_location' => 'home'],
        ]);

        $this->assertTrue($filters['home_friendly'] ?? false);
        $this->assertSame('home_calisthenics', $filters['type'] ?? null);
    }

    public function test_coach_message_returns_grounded_exercises_and_rejects_fake_provider_ids(): void
    {
        $real = Exercise::query()->active()->first();
        $this->assertNotNull($real);

        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'Try these chest moves.',
                'provider' => 'mock',
                'language' => 'en',
                'grounded_exercises' => [
                    ['exercise_id' => $real->id, 'name' => $real->name_en, 'grounded' => true],
                    ['exercise_id' => 999999, 'name' => 'Fake Lift', 'grounded' => true],
                ],
            ], 200),
        ]);

        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/coach/conversations')->json('data.id');
        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'Give me a chest exercise',
            'language' => 'en',
        ]);

        $response->assertCreated();
        $grounded = $response->json('data.grounded_exercises');
        $this->assertIsArray($grounded);
        $this->assertNotEmpty($grounded);

        $ids = collect($grounded)->pluck('exercise_id')->all();
        $this->assertContains($real->id, $ids);
        $this->assertNotContains(999999, $ids);

        foreach ($grounded as $row) {
            $this->assertTrue($row['grounded']);
            $this->assertDatabaseHas('exercises', ['id' => $row['exercise_id'], 'is_active' => 1]);
        }

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['exercise_candidates'])
                && is_array($data['exercise_candidates']);
        });
    }

    private function makeUser(string $email = 'grounding@test.com'): User
    {
        $user = User::query()->create([
            'name' => 'Grounding',
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
