<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Models\WaterIntake;
use App\Services\HydrationGoalService;
use App\Services\UserAiContextBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HydrationApiTest extends TestCase
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

    public function test_unauthenticated_cannot_access_hydration(): void
    {
        $this->getJson('/api/hydration/today')->assertUnauthorized();
        $this->postJson('/api/hydration/intakes', ['amount_ml' => 250])->assertUnauthorized();
    }

    public function test_add_intake_and_today_summary(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/hydration/intakes', [
            'amount_ml' => 500,
            'timezone' => 'Asia/Riyadh',
        ])->assertCreated()
            ->assertJsonPath('data.today.consumed_ml', 500);

        $this->postJson('/api/hydration/intakes', [
            'amount_ml' => 250,
            'timezone' => 'Asia/Riyadh',
        ])->assertCreated();

        $this->getJson('/api/hydration/today?timezone=Asia/Riyadh')
            ->assertOk()
            ->assertJsonPath('data.consumed_ml', 750)
            ->assertJsonPath('data.intake_count', 2);
    }

    public function test_progress_and_remaining_are_server_calculated(): void
    {
        $user = $this->makeUser();
        $user->profile->update([
            'water_goal_ml' => 2500,
            'water_goal_is_custom' => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/hydration/intakes', ['amount_ml' => 1750, 'timezone' => 'UTC'])
            ->assertCreated();

        $this->getJson('/api/hydration/today?timezone=UTC')
            ->assertOk()
            ->assertJsonPath('data.goal_ml', 2500)
            ->assertJsonPath('data.consumed_ml', 1750)
            ->assertJsonPath('data.remaining_ml', 750)
            ->assertJsonPath('data.progress', 0.7);
    }

    public function test_delete_own_intake_updates_total(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/hydration/intakes', ['amount_ml' => 500, 'timezone' => 'UTC'])
            ->assertCreated()
            ->json('data.intake.id');

        $this->deleteJson('/api/hydration/intakes/'.$create.'?timezone=UTC')
            ->assertOk()
            ->assertJsonPath('data.today.consumed_ml', 0);
    }

    public function test_user_cannot_delete_another_users_intake(): void
    {
        $a = $this->makeUser('a@test.com');
        $b = $this->makeUser('b@test.com');

        $intake = WaterIntake::query()->create([
            'user_id' => $a->id,
            'amount_ml' => 500,
            'consumed_at' => now(),
        ]);

        Sanctum::actingAs($b);
        $this->deleteJson('/api/hydration/intakes/'.$intake->id)->assertNotFound();
        $this->assertDatabaseHas('water_intakes', ['id' => $intake->id, 'amount_ml' => 500]);
    }

    public function test_user_isolation_on_today(): void
    {
        $a = $this->makeUser('a@test.com');
        $b = $this->makeUser('b@test.com');

        WaterIntake::query()->create([
            'user_id' => $a->id,
            'amount_ml' => 500,
            'consumed_at' => now(),
        ]);

        Sanctum::actingAs($b);
        $this->getJson('/api/hydration/today?timezone=UTC')
            ->assertOk()
            ->assertJsonPath('data.consumed_ml', 0);
    }

    public function test_invalid_amount_rejected(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->postJson('/api/hydration/intakes', ['amount_ml' => 0])->assertStatus(422);
        $this->postJson('/api/hydration/intakes', ['amount_ml' => -10])->assertStatus(422);
        $this->postJson('/api/hydration/intakes', ['amount_ml' => 99999])->assertStatus(422);
    }

    public function test_custom_goal_not_silently_overwritten_by_recommendation(): void
    {
        $user = $this->makeUser();
        $user->profile->update([
            'weight_kg' => 82,
            'activity_level' => 'moderate',
            'training_frequency' => 4,
            'water_goal_ml' => 2200,
            'water_goal_is_custom' => true,
        ]);

        $goals = app(HydrationGoalService::class);
        $recommended = $goals->recommend($user->fresh(['profile']));
        $this->assertNotSame(2200, $recommended);
        $this->assertSame(2200, $goals->effectiveGoal($user->fresh(['profile'])));
    }

    public function test_update_goal(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->putJson('/api/hydration/goal', ['goal_ml' => 2800])
            ->assertOk()
            ->assertJsonPath('data.goal_ml', 2800)
            ->assertJsonPath('data.goal_is_custom', true);
    }

    public function test_ai_context_includes_hydration_and_is_isolated(): void
    {
        $a = $this->makeUser('a@test.com');
        $b = $this->makeUser('b@test.com');
        $a->profile->update(['water_goal_ml' => 2500, 'water_goal_is_custom' => true]);
        $b->profile->update(['water_goal_ml' => 3000, 'water_goal_is_custom' => true]);

        WaterIntake::query()->create(['user_id' => $a->id, 'amount_ml' => 1750, 'consumed_at' => now()]);
        WaterIntake::query()->create(['user_id' => $b->id, 'amount_ml' => 1000, 'consumed_at' => now()]);

        $builder = app(UserAiContextBuilder::class);
        $ctxA = $builder->forCoach($a->fresh(['profile']));
        $ctxB = $builder->forCoach($b->fresh(['profile']));

        $this->assertSame(2500, $ctxA['hydration']['goal_ml']);
        $this->assertSame(1750, $ctxA['hydration']['consumed_ml']);
        $this->assertSame(750, $ctxA['hydration']['remaining_ml']);
        $this->assertSame(3000, $ctxB['hydration']['goal_ml']);
        $this->assertSame(1000, $ctxB['hydration']['consumed_ml']);
        $this->assertNotEquals($ctxA['hydration']['consumed_ml'], $ctxB['hydration']['consumed_ml']);
    }

    public function test_history_returns_daily_totals(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        WaterIntake::query()->create([
            'user_id' => $user->id,
            'amount_ml' => 400,
            'consumed_at' => Carbon::now('UTC')->subDay(),
        ]);

        $this->getJson('/api/hydration/history?days=7&timezone=UTC')
            ->assertOk()
            ->assertJsonStructure(['data' => ['days', 'streak_days']]);
    }

    private function makeUser(string $email = 'hydrate@test.com'): User
    {
        $user = User::query()->create([
            'name' => 'Hydrate User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 2,
        ]);
        Profile::query()->create([
            'user_id' => $user->id,
            'locale' => 'en',
            'weight_kg' => 75,
        ]);

        return $user->fresh(['profile']);
    }
}
