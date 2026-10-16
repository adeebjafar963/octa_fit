<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeatureFlagsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
    }

    public function test_features_endpoint_requires_auth(): void
    {
        $this->getJson('/api/features')->assertUnauthorized();
    }

    public function test_features_endpoint_returns_defaults_enabled(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/features')
            ->assertOk()
            ->assertJsonPath('data.coach_enabled', true)
            ->assertJsonPath('data.physio_enabled', true)
            ->assertJsonPath('data.motion_enabled', true);
    }

    public function test_disabled_coach_blocks_coach_routes(): void
    {
        Setting::query()->create([
            'key' => 'feature_coach_enabled',
            'value' => '0',
            'group' => 'features',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/coach/conversations')
            ->assertForbidden()
            ->assertJsonPath('message', 'This feature is currently disabled.');

        $this->getJson('/api/features')
            ->assertOk()
            ->assertJsonPath('data.coach_enabled', false);
    }

    public function test_disabled_physio_and_motion_block_routes(): void
    {
        Setting::query()->insert([
            [
                'key' => 'feature_physio_enabled',
                'value' => '0',
                'group' => 'features',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'feature_motion_enabled',
                'value' => '0',
                'group' => 'features',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/physio/assessments')->assertForbidden();
        $this->getJson('/api/motion/exercises')->assertForbidden();
    }
}
