<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Athlete',
            'email' => 'athlete@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'athlete@example.com')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'athlete@example.com']);
        $this->assertDatabaseHas('profiles', [
            'user_id' => User::query()->where('email', 'athlete@example.com')->value('id'),
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_registration_validation_errors(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors']);
    }

    public function test_login_success(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);
        Profile::query()->create(['user_id' => $user->id, 'locale' => 'en']);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_login_rejects_unknown_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'missing@example.com',
            'password' => 'Password123!',
        ])->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        Profile::query()->create([
            'user_id' => $user->id,
            'locale' => 'ar',
            'age' => 28,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.role.name', 'user')
            ->assertJsonPath('data.user.profile.age', 28);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, PersonalAccessToken::query()->count());

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_profile_update(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        Profile::query()->create(['user_id' => $user->id, 'locale' => 'en']);

        Sanctum::actingAs($user);

        $this->putJson('/api/profile', [
            'name' => 'New Name',
            'age' => 30,
            'gender' => 'female',
            'height_cm' => 168.5,
            'weight_kg' => 62,
            'training_goal' => 'fat_loss',
            'locale' => 'ar',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'New Name')
            ->assertJsonPath('data.user.profile.training_goal', 'fat_loss')
            ->assertJsonPath('data.user.profile.locale', 'ar');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_password_change_revokes_old_tokens_and_returns_new_token(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1!']);
        Profile::query()->create(['user_id' => $user->id]);
        $oldToken = $user->createToken('mobile')->plainTextToken;

        $response = $this->withToken($oldToken)
            ->putJson('/api/profile/password', [
                'current_password' => 'OldPassword1!',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $newToken = $response->json('data.token');
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(1, PersonalAccessToken::query()->count());

        $this->app['auth']->forgetGuards();

        $this->withToken($oldToken)
            ->getJson('/api/me')
            ->assertUnauthorized();

        $this->withToken($newToken)
            ->getJson('/api/me')
            ->assertOk();
    }

    public function test_admin_role_is_returned(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);
        Profile::query()->create(['user_id' => $admin->id]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.role.name', 'admin');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_role_middleware_blocks_non_admin(): void
    {
        $user = User::factory()->create();

        $middleware = new \App\Http\Middleware\EnsureUserHasRole;
        $response = $middleware->handle(
            tap(request(), fn ($request) => $request->setUserResolver(fn () => $user)),
            fn () => response()->json(['ok' => true]),
            'admin',
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse(json_decode($response->getContent(), true)['success']);
    }
}
