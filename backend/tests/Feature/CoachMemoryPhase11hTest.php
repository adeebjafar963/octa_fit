<?php

namespace Tests\Feature;

use App\Models\CoachMemory;
use App\Models\NutritionPreference;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\Memory\CoachMemoryExtractor;
use App\Services\Coach\Memory\CoachMemoryService;
use App\Services\Coach\TrainingIntentExtractor;
use App\Services\UserAiContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 11H — Coach memory CRUD, extraction, context, authority hierarchy.
 */
class CoachMemoryPhase11hTest extends TestCase
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

    public function test_memory_create_update_deactivate_and_clear(): void
    {
        $user = $this->makeUser();
        $svc = app(CoachMemoryService::class);

        $m = $svc->upsert($user, CoachMemory::CATEGORY_EQUIPMENT, 'available_equipment', [
            'items' => ['dumbbells'],
            'mode' => 'dumbbell',
        ]);
        $this->assertTrue($m->active);
        $this->assertSame('dumbbell', $m->value['mode']);

        $m2 = $svc->upsert($user, CoachMemory::CATEGORY_EQUIPMENT, 'available_equipment', [
            'items' => ['dumbbells', 'bands'],
            'mode' => 'dumbbell',
        ]);
        $this->assertSame($m->id, $m2->id);
        $this->assertCount(1, CoachMemory::query()->forUser($user)->get());

        $svc->deactivate($user, $m2->fresh());
        $this->assertFalse($m2->fresh()->active);

        $svc->upsert($user, CoachMemory::CATEGORY_SCHEDULE, 'preferred_training_time', ['slot' => 'morning']);
        // clearAll removes active + inactive rows for the user
        $this->assertSame(2, $svc->clearAll($user));
        $this->assertSame(0, CoachMemory::query()->forUser($user)->count());
    }

    public function test_user_isolation_on_api(): void
    {
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');
        $svc = app(CoachMemoryService::class);
        $mem = $svc->upsert($a, CoachMemory::CATEGORY_GOAL, 'primary_goal', ['goal' => 'hypertrophy']);

        Sanctum::actingAs($b);
        $this->postJson("/api/coach/memories/{$mem->id}/deactivate")
            ->assertForbidden();

        Sanctum::actingAs($a);
        $this->getJson('/api/coach/memories')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.schema_version', '11h.1');
    }

    public function test_duplicate_prevention_same_category_key(): void
    {
        $user = $this->makeUser();
        $svc = app(CoachMemoryService::class);
        $svc->upsert($user, CoachMemory::CATEGORY_FOOD, 'dislike_tuna', ['food' => 'tuna']);
        $svc->upsert($user, CoachMemory::CATEGORY_FOOD, 'dislike_tuna', ['food' => 'tuna', 'polarity' => 'dislike']);
        $this->assertSame(1, CoachMemory::query()->forUser($user)->count());
    }

    public function test_explicit_hate_tuna_syncs_11e_and_memory(): void
    {
        $user = $this->makeUser();
        $result = app(CoachMemoryExtractor::class)->processUserMessage($user, 'I hate tuna.');
        $this->assertTrue($result['changed']);
        $this->assertTrue($result['nutrition_synced']);

        $pref = NutritionPreference::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($pref);
        $this->assertContains('tuna', $pref->dislikeList());

        $ctx = app(UserAiContextBuilder::class)->forCoach($user, 'Create my meal plan.');
        $this->assertSame('11h.1', $ctx['memory_context']['schema_version']);
        $this->assertContains('tuna', $ctx['nutrition']['preferences']['disliked']);
        $this->assertTrue($ctx['capabilities']['coach_memory']);
    }

    public function test_temporary_replace_does_not_create_memory(): void
    {
        $user = $this->makeUser();
        $result = app(CoachMemoryExtractor::class)->processUserMessage(
            $user,
            'Replace tuna in this meal.',
        );
        $this->assertFalse($result['changed']);
        $this->assertSame('temporary_action', $result['skipped_reason']);
        $this->assertSame(0, CoachMemory::query()->forUser($user)->count());
    }

    public function test_uncertain_statement_does_not_create_confirmed_memory(): void
    {
        $user = $this->makeUser();
        $result = app(CoachMemoryExtractor::class)->processUserMessage(
            $user,
            'I might start training in the morning.',
        );
        $this->assertFalse($result['changed']);
        $this->assertSame('uncertain', $result['skipped_reason']);
        $this->assertSame(0, CoachMemory::query()->forUser($user)->where('active', true)->count());
    }

    public function test_equipment_memory_affects_training_intent(): void
    {
        $user = $this->makeUser();
        app(CoachMemoryExtractor::class)->processUserMessage($user, 'I only have dumbbells at home.');
        $ctx = app(UserAiContextBuilder::class)->forCoach($user, 'Give me a workout plan.');
        $intent = app(TrainingIntentExtractor::class)->extract('Give me a 4-day workout plan.', $ctx);
        $this->assertSame('dumbbell', $intent['equipment_mode']);
    }

    public function test_current_gym_request_overrides_home_memory(): void
    {
        $user = $this->makeUser();
        app(CoachMemoryService::class)->upsert(
            $user,
            CoachMemory::CATEGORY_TRAINING,
            'environment',
            ['text' => 'home'],
        );
        $ctx = app(UserAiContextBuilder::class)->forCoach($user, 'I joined a gym. Give me a gym workout plan.');
        $intent = app(TrainingIntentExtractor::class)->extract(
            'I joined a gym. Give me a gym workout plan.',
            $ctx,
        );
        $this->assertSame('gym', $intent['equipment_mode']);
    }

    public function test_current_conditioning_request_overrides_memory_goal(): void
    {
        $user = $this->makeUser();
        app(CoachMemoryService::class)->upsert(
            $user,
            CoachMemory::CATEGORY_GOAL,
            'primary_goal',
            ['goal' => 'hypertrophy'],
        );
        $ctx = app(UserAiContextBuilder::class)->forCoach($user, 'I want to focus on MMA conditioning.');
        $intent = app(TrainingIntentExtractor::class)->extract(
            'I want to focus on MMA conditioning. Give me a program.',
            $ctx,
        );
        $this->assertSame('conditioning', $intent['goal']);
    }

    public function test_schedule_memory_stored(): void
    {
        $user = $this->makeUser();
        $result = app(CoachMemoryExtractor::class)->processUserMessage($user, 'I usually train at 7 AM.');
        $this->assertTrue($result['changed']);
        $row = CoachMemory::query()->forUser($user)->where('key', 'preferred_training_time')->first();
        $this->assertNotNull($row);
        $this->assertSame(7, $row->value['hour'] ?? null);
    }

    public function test_inactive_and_expired_excluded_from_context(): void
    {
        $user = $this->makeUser();
        $svc = app(CoachMemoryService::class);
        $active = $svc->upsert($user, CoachMemory::CATEGORY_COACHING, 'answer_style', ['style' => 'concise']);
        $expired = $svc->upsert(
            $user,
            CoachMemory::CATEGORY_SCHEDULE,
            'temp_slot',
            ['slot' => 'evening'],
            CoachMemory::SOURCE_EXPLICIT,
            1.0,
            true,
            now()->subDay(),
        );
        $svc->deactivate($user, $active);

        $ctx = $svc->buildContext($user, 'general tip');
        $keys = array_column($ctx['memories'], 'key');
        $this->assertNotContains('answer_style', $keys);
        $this->assertNotContains('temp_slot', $keys);
        $this->assertFalse($expired->fresh()->active);
    }

    public function test_relevance_filters_nutrition_vs_training(): void
    {
        $user = $this->makeUser();
        $svc = app(CoachMemoryService::class);
        $svc->upsert($user, CoachMemory::CATEGORY_FOOD, 'dislike_broccoli', ['food' => 'broccoli']);
        $svc->upsert($user, CoachMemory::CATEGORY_EQUIPMENT, 'available_equipment', [
            'items' => ['dumbbells'],
            'mode' => 'dumbbell',
        ]);

        $nutritionCtx = $svc->buildContext($user, 'Build my meal plan with protein.');
        $nutritionCats = array_column($nutritionCtx['memories'], 'category');
        $this->assertContains(CoachMemory::CATEGORY_FOOD, $nutritionCats);

        $trainingCtx = $svc->buildContext($user, 'Give me a chest workout program.');
        $trainingCats = array_column($trainingCtx['memories'], 'category');
        $this->assertContains(CoachMemory::CATEGORY_EQUIPMENT, $trainingCats);
    }

    public function test_profile_frequency_overrides_stale_memory_days(): void
    {
        $user = $this->makeUser();
        $user->profile->update(['training_frequency' => 5]);
        app(CoachMemoryService::class)->upsert(
            $user,
            CoachMemory::CATEGORY_TRAINING,
            'days_per_week',
            ['days' => 3],
        );
        $ctx = app(UserAiContextBuilder::class)->forCoach($user->fresh(['profile']), 'Create my weekly plan.');
        $intent = app(TrainingIntentExtractor::class)->extract('Create my weekly training plan.', $ctx);
        $this->assertSame(5, $intent['days_per_week']);
    }

    public function test_api_deactivate_and_clear(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $mem = app(CoachMemoryService::class)->upsert(
            $user,
            CoachMemory::CATEGORY_COACHING,
            'answer_style',
            ['style' => 'detailed'],
        );

        $this->postJson("/api/coach/memories/{$mem->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.active', false);

        app(CoachMemoryService::class)->upsert(
            $user,
            CoachMemory::CATEGORY_GOAL,
            'primary_goal',
            ['goal' => 'strength'],
        );
        $this->deleteJson('/api/coach/memories')
            ->assertOk();
        $this->assertSame(0, CoachMemory::query()->forUser($user)->count());
    }

    private function makeUser(string $email = 'memory@example.com'): User
    {
        $user = User::query()->create([
            'name' => 'Memory User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => 2,
            'is_active' => true,
        ]);
        Profile::query()->create([
            'user_id' => $user->id,
            'age' => 28,
            'gender' => 'male',
            'height_cm' => 175,
            'weight_kg' => 80,
            'fitness_level' => 'intermediate',
            'training_goal' => 'muscle_gain',
            'training_frequency' => 4,
            'training_location' => 'gym',
            'equipment' => ['barbell'],
            'locale' => 'en',
            'profile_completed_at' => now(),
        ]);

        return $user->fresh(['profile']);
    }
}
