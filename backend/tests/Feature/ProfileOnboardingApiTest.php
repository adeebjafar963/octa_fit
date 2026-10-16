<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAiContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileOnboardingApiTest extends TestCase
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

    public function test_completion_incomplete_for_new_profile(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile/completion');
        $response->assertOk()
            ->assertJsonPath('data.profile_complete', false)
            ->assertJsonPath('data.completion_percentage', 0);
    }

    public function test_onboarding_update_persists_and_completes(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $payload = [
            'age' => 25,
            'gender' => 'male',
            'height_cm' => 180,
            'weight_kg' => 82,
            'fitness_level' => 'intermediate',
            'training_experience' => '1_3_years',
            'training_goal' => 'muscle_gain',
            'training_frequency' => 4,
            'training_location' => 'gym',
            'equipment' => ['barbell', 'dumbbell'],
            'injury_status' => 'none',
            'onboarding_step' => 8,
        ];

        $this->putJson('/api/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.user.profile_complete', true)
            ->assertJsonPath('data.user.profile.weight_kg', 82)
            ->assertJsonPath('data.user.profile.training_goal', 'muscle_gain');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'fitness_level' => 'intermediate',
            'injury_status' => 'none',
        ]);

        $this->assertNotNull($user->fresh()->profile->profile_completed_at);
    }

    public function test_user_cannot_read_another_users_injury_description_via_me(): void
    {
        $a = $this->makeUser('a@test.com');
        $b = $this->makeUser('b@test.com');
        $b->profile->update([
            'injury_status' => 'current',
            'injury_description' => 'secret knee pain',
            'height_cm' => 170,
            'weight_kg' => 70,
            'fitness_level' => 'beginner',
            'training_goal' => 'fat_loss',
            'training_experience' => 'none',
        ]);

        Sanctum::actingAs($a);
        $this->getJson('/api/me')->assertOk();

        // A's me should not contain B's data at all.
        $this->assertDatabaseHas('profiles', ['user_id' => $b->id, 'injury_description' => 'secret knee pain']);
    }

    public function test_validation_rejects_absurd_height(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', ['height_cm' => 10])
            ->assertStatus(422);
    }

    public function test_validation_rejects_non_binary_gender_values(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', ['gender' => 'other'])
            ->assertStatus(422);
        $this->putJson('/api/profile', ['gender' => 'prefer_not'])
            ->assertStatus(422);
        $this->putJson('/api/profile', ['gender' => 'female'])
            ->assertOk()
            ->assertJsonPath('data.user.profile.gender', 'female');
    }

    public function test_ai_context_differs_between_users(): void
    {
        $a = $this->makeUser('a@test.com');
        $b = $this->makeUser('b@test.com');

        $a->profile->update([
            'height_cm' => 180,
            'weight_kg' => 82,
            'fitness_level' => 'intermediate',
            'training_goal' => 'muscle_gain',
            'training_experience' => '1_3_years',
            'injury_status' => 'none',
        ]);
        $b->profile->update([
            'height_cm' => 165,
            'weight_kg' => 60,
            'fitness_level' => 'beginner',
            'training_goal' => 'fat_loss',
            'training_experience' => 'none',
            'injury_status' => 'none',
        ]);

        $builder = app(UserAiContextBuilder::class);
        $ctxA = $builder->forCoach($a->fresh(['profile']));
        $ctxB = $builder->forCoach($b->fresh(['profile']));

        $this->assertSame(82.0, $ctxA['weight_kg']);
        $this->assertSame('muscle_gain', $ctxA['primary_goal']);
        $this->assertSame(60.0, $ctxB['weight_kg']);
        $this->assertSame('fat_loss', $ctxB['primary_goal']);
        $this->assertNotEquals($ctxA['weight_kg'], $ctxB['weight_kg']);
    }

    public function test_coach_request_includes_fresh_user_context(): void
    {
        Http::fake([
            '*/chat' => Http::response(['message' => 'ok', 'provider' => 'mock', 'language' => 'en'], 200),
        ]);

        $user = $this->makeUser();
        $user->profile->update([
            'height_cm' => 180,
            'weight_kg' => 82,
            'fitness_level' => 'intermediate',
            'training_goal' => 'muscle_gain',
            'training_experience' => '1_3_years',
            'injury_status' => 'none',
            'profile_completed_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/coach/conversations')->assertCreated();
        $id = \App\Models\CoachConversation::query()->where('user_id', $user->id)->value('id');
        $this->assertNotNull($id);
        $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'I want a workout for today.',
            'language' => 'en',
        ])->assertCreated();

        Http::assertSent(function ($request) {
            $data = $request->data();
            return isset($data['user_context']['weight_kg'])
                && (float) $data['user_context']['weight_kg'] === 82.0
                && ($data['user_context']['primary_goal'] ?? null) === 'muscle_gain';
        });

        $user->profile->update(['weight_kg' => 85, 'training_goal' => 'fat_loss']);

        $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'I want a workout for today.',
            'language' => 'en',
        ])->assertCreated();

        Http::assertSent(function ($request) {
            $data = $request->data();
            return isset($data['user_context']['weight_kg'])
                && (float) $data['user_context']['weight_kg'] === 85.0
                && ($data['user_context']['primary_goal'] ?? null) === 'fat_loss';
        });
    }

    private function makeUser(string $email = 'user@test.com'): User
    {
        $user = User::query()->create([
            'name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 2,
            'is_active' => true,
        ]);
        Profile::query()->create([
            'user_id' => $user->id,
            'locale' => 'en',
        ]);

        return $user->load('profile');
    }
}
