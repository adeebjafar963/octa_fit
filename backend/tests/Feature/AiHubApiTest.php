<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiRouterService;
use App\Services\UserAiContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiHubApiTest extends TestCase
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

    public function test_hub_requires_authentication(): void
    {
        $this->getJson('/api/ai/hub')->assertUnauthorized();
        $this->postJson('/api/ai/route', ['message' => 'hello'])->assertUnauthorized();
    }

    public function test_hub_returns_capabilities_and_greeting(): void
    {
        $user = $this->makeUser();
        $user->profile->update([
            'training_goal' => 'muscle_gain',
            'fitness_level' => 'intermediate',
            'weight_kg' => 82,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/ai/hub')
            ->assertOk()
            ->assertJsonPath('data.capabilities.coach', true)
            ->assertJsonPath('data.capabilities.physio', true)
            ->assertJsonPath('data.capabilities.motion', true)
            ->assertJsonPath('data.greeting.name', $user->name)
            ->assertJsonPath('data.greeting.training_goal', 'muscle_gain');
    }

    public function test_route_coach_training_request(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/ai/route', [
            'message' => 'Create today\'s workout for muscle gain',
            'language' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'coach')
            ->assertJsonPath('data.capability', 'coach')
            ->assertJsonPath('data.action', 'open_coach_chat')
            ->assertJsonPath('data.navigation.creates_conversation', true);
    }

    public function test_route_physio_pain_request(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/ai/route', [
            'message' => 'ركبتي تؤلمني بعد السكوات',
            'language' => 'ar',
        ])
            ->assertOk()
            ->assertJsonPath('data.capability', 'physio')
            ->assertJsonPath('data.action', 'open_physio_new');
    }

    public function test_route_motion_form_request(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/ai/route', [
            'message' => 'هل وضعيتي صحيحة أثناء السكوات؟ حلل الفورم',
            'language' => 'ar',
        ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'motion')
            ->assertJsonPath('data.action', 'open_motion');
    }

    public function test_route_safety_priority_over_coach(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/ai/route', [
            'message' => 'I have severe chest pain and difficulty breathing',
        ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'safety')
            ->assertJsonPath('data.priority', 'safety')
            ->assertJsonPath('data.capability', 'physio');
    }

    public function test_route_rejects_when_feature_disabled(): void
    {
        Setting::query()->create([
            'key' => 'feature_coach_enabled',
            'value' => '0',
            'group' => 'features',
        ]);
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/ai/route', [
            'message' => 'Help me with my training plan today',
        ])->assertForbidden();
    }

    public function test_ai_context_differs_between_users_for_same_question(): void
    {
        $a = $this->makeUser('a@test.com');
        $b = $this->makeUser('b@test.com');
        $a->profile->update([
            'weight_kg' => 82,
            'training_goal' => 'muscle_gain',
            'fitness_level' => 'intermediate',
        ]);
        $b->profile->update([
            'weight_kg' => 60,
            'training_goal' => 'fat_loss',
            'fitness_level' => 'beginner',
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

    public function test_profile_weight_update_reflected_in_context(): void
    {
        $user = $this->makeUser();
        $user->profile->update(['weight_kg' => 82, 'training_goal' => 'muscle_gain']);

        $builder = app(UserAiContextBuilder::class);
        $this->assertSame(82.0, $builder->forCoach($user->fresh(['profile']))['weight_kg']);

        $user->profile->update(['weight_kg' => 85]);
        $this->assertSame(85.0, $builder->forCoach($user->fresh(['profile']))['weight_kg']);
    }

    public function test_router_service_unit_intents(): void
    {
        $router = app(AiRouterService::class);
        $this->assertSame('physio', $router->route('something hurts in my knee')['capability']);
        $this->assertSame('motion', $router->route('analyze my squat form posture')['capability']);
        $this->assertSame('coach', $router->route('how do I increase protein?')['capability']);
    }

    private function makeUser(string $email = 'user@test.com'): User
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 2,
        ]);
        Profile::query()->create([
            'user_id' => $user->id,
            'locale' => 'en',
        ]);

        return $user->fresh(['profile']);
    }
}
