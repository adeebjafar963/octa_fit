<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAiContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoachUserContextPhase11aTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->insert([
            ['id' => 1, 'name' => 'admin', 'display_name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'user', 'display_name' => 'User', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_for_coach_emits_schema_11a_nested_sections(): void
    {
        $user = $this->makeUser();
        $user->profile->update([
            'age' => 29,
            'gender' => 'male',
            'height_cm' => 178,
            'weight_kg' => 84.5,
            'fitness_level' => 'intermediate',
            'training_goal' => 'muscle_gain',
            'training_experience' => '1_3_years',
            'training_frequency' => 4,
            'session_duration_minutes' => 60,
            'training_location' => 'gym',
            'equipment' => ['barbell', 'dumbbells'],
            'activity_level' => 'moderately_active',
            'dietary_preference' => 'omnivore',
            'meals_per_day' => 4,
            'sleep_hours' => 7.5,
            'injury_status' => 'none',
            'locale' => 'en',
            'profile_completed_at' => now(),
        ]);

        $ctx = app(UserAiContextBuilder::class)->forCoach($user->fresh(['profile']));

        $this->assertSame(UserAiContextBuilder::SCHEMA_VERSION, $ctx['schema_version']);
        $this->assertTrue($ctx['available']);
        $this->assertSame($user->id, $ctx['identity']['user_id']);
        $this->assertSame('metric', $ctx['identity']['unit_system']);
        $this->assertSame('male', $ctx['body']['sex']);
        $this->assertSame(84.5, $ctx['body']['weight_kg']);
        $this->assertSame('muscle_gain', $ctx['goals']['primary_goal']);
        $this->assertSame('intermediate', $ctx['training']['fitness_level']);
        $this->assertSame(4, $ctx['training']['training_frequency_per_week']);
        $this->assertSame(['barbell', 'dumbbells'], $ctx['training']['equipment']);
        $this->assertSame(4, $ctx['nutrition']['meals_per_day']);
        $this->assertSame(7.5, $ctx['recovery']['sleep_hours']);
        $this->assertIsArray($ctx['nutrition']['preferences']['allergies']);
        $this->assertSame([], $ctx['nutrition']['preferences']['allergies']);
        $this->assertNull($ctx['active_plan']);
        $this->assertTrue($ctx['capabilities']['exercise_linking']);
        $this->assertArrayHasKey('recent_training_types', $ctx['workout']);
        $this->assertArrayHasKey('last_completed_at', $ctx['workout']);

        // Flat aliases for backward compatibility.
        $this->assertSame(84.5, $ctx['weight_kg']);
        $this->assertSame('muscle_gain', $ctx['primary_goal']);
        $this->assertSame(4, $ctx['meals_per_day']);
        $this->assertSame(7.5, $ctx['sleep_hours']);
    }

    public function test_for_coach_without_profile_is_unavailable(): void
    {
        $user = User::query()->create([
            'name' => 'No Profile',
            'email' => 'noprofile@test.com',
            'password' => Hash::make('password123'),
            'role_id' => 2,
            'is_active' => true,
        ]);

        $ctx = app(UserAiContextBuilder::class)->forCoach($user);

        $this->assertFalse($ctx['available']);
        $this->assertSame(UserAiContextBuilder::SCHEMA_VERSION, $ctx['schema_version']);
    }

    private function makeUser(string $email = 'coach-ctx@test.com'): User
    {
        $user = User::query()->create([
            'name' => 'Coach Ctx',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 2,
            'is_active' => true,
        ]);
        Profile::query()->create([
            'user_id' => $user->id,
            'locale' => 'en',
        ]);

        return $user->fresh(['profile']);
    }
}
