<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MotionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
    }

    private function fakeMotionSuccess(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'exercise' => 'squat',
                'repetitions' => 8,
                'duration_seconds' => 24.5,
                'confidence' => 0.80,
                'movement_quality' => 'good',
                'observations' => ['Good squat depth detected.'],
                'tips' => ['Keep your chest stable.'],
                'metrics' => [
                    'average_knee_angle' => 91.2,
                    'minimum_knee_angle' => 78.4,
                ],
                'provider' => 'mock',
                'frames_analyzed' => 120,
            ], 200),
        ]);
    }

    public function test_analyze_requires_auth(): void
    {
        $this->post('/api/motion/analyze', [
            'exercise' => 'squat',
        ])->assertUnauthorized();
    }

    public function test_list_exercises_requires_auth(): void
    {
        $this->getJson('/api/motion/exercises')->assertUnauthorized();
    }

    public function test_list_exercises(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/motion/exercises')
            ->assertOk()
            ->assertJsonPath('data.items.0', 'squat')
            ->assertJsonPath('data.items.1', 'deadlift')
            ->assertJsonCount(2, 'data.items');
    }

    public function test_successful_mock_analysis(): void
    {
        $this->fakeMotionSuccess();
        Sanctum::actingAs(User::factory()->create());

        $video = UploadedFile::fake()->create('squat.mp4', 500, 'video/mp4');

        $response = $this->post('/api/motion/analyze', [
            'exercise' => 'squat',
            'video' => $video,
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.exercise', 'squat')
            ->assertJsonPath('data.repetitions', 8)
            ->assertJsonPath('data.movement_quality', 'good')
            ->assertJsonPath('data.confidence', 0.80);
    }

    public function test_unsupported_exercise(): void
    {
        $this->fakeMotionSuccess();
        Sanctum::actingAs(User::factory()->create());

        $video = UploadedFile::fake()->create('demo.mp4', 100, 'video/mp4');

        $this->post('/api/motion/analyze', [
            'exercise' => 'cartwheel',
            'video' => $video,
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_missing_video(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/motion/analyze', [
            'exercise' => 'squat',
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_unsupported_file_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $this->post('/api/motion/analyze', [
            'exercise' => 'squat',
            'video' => $file,
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_fastapi_unavailable(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false], 500),
        ]);

        Sanctum::actingAs(User::factory()->create());
        $video = UploadedFile::fake()->create('squat.mp4', 100, 'video/mp4');

        $this->post('/api/motion/analyze', [
            'exercise' => 'squat',
            'video' => $video,
        ], ['Accept' => 'application/json'])->assertStatus(502);
    }
}
