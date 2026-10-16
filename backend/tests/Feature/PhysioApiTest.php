<?php

namespace Tests\Feature;

use App\Models\PhysioAssessment;
use App\Models\PhysioMessage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhysioApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakePhysioSuccess(array $overrides = []): void
    {
        $body = array_merge([
            'success' => true,
            'risk_level' => 'low',
            'requires_urgent_care' => false,
            'summary' => 'Your description is often consistent with delayed muscle soreness.',
            'possible_explanations' => ['Delayed onset muscle soreness'],
            'self_care' => ['Light movement', 'Hydration'],
            'avoid' => ['Very hard sessions immediately'],
            'professional_care' => 'Seek advice if symptoms persist.',
            'red_flags' => ['Severe swelling'],
            'provider' => 'mock',
            'language' => 'en',
        ], $overrides);

        Http::fake([
            '*' => Http::response($body, 200),
        ]);
    }

    public function test_assessments_require_auth(): void
    {
        $this->getJson('/api/physio/assessments')->assertUnauthorized();
        $this->postJson('/api/physio/assessments', [])->assertUnauthorized();
    }

    public function test_create_valid_assessment(): void
    {
        $this->fakePhysioSuccess();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/physio/assessments', [
            'body_area' => 'legs',
            'symptoms' => 'muscle soreness after workout',
            'pain_level' => 3,
            'duration' => '1 day',
            'language' => 'en',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.body_area', 'legs')
            ->assertJsonPath('data.risk_level', 'low')
            ->assertJsonPath('data.requires_urgent_care', false)
            ->assertJsonPath('data.result.summary', 'Your description is often consistent with delayed muscle soreness.');

        $this->assertDatabaseCount('physio_assessments', 1);
        $this->assertDatabaseCount('physio_messages', 2);
    }

    public function test_invalid_body_area_and_pain_level(): void
    {
        $this->fakePhysioSuccess();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/physio/assessments', [
            'body_area' => 'brain',
            'symptoms' => 'pain',
        ])->assertStatus(422);

        $this->postJson('/api/physio/assessments', [
            'body_area' => 'knee',
            'symptoms' => 'pain',
            'pain_level' => 11,
        ])->assertStatus(422);
    }

    public function test_oversized_symptoms_rejected(): void
    {
        $this->fakePhysioSuccess();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/physio/assessments', [
            'body_area' => 'knee',
            'symptoms' => str_repeat('a', 2001),
        ])->assertStatus(422);
    }

    public function test_ownership_enforced(): void
    {
        $this->fakePhysioSuccess();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/physio/assessments', [
            'body_area' => 'knee',
            'symptoms' => 'mild discomfort after squats',
            'pain_level' => 3,
        ])->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson('/api/physio/assessments/'.$id)->assertForbidden();
        $this->postJson('/api/physio/assessments/'.$id.'/messages', [
            'message' => 'Can I train?',
        ])->assertForbidden();
        $this->deleteJson('/api/physio/assessments/'.$id)->assertForbidden();
    }

    public function test_follow_up_message_saved(): void
    {
        $this->fakePhysioSuccess([
            'summary' => 'You may return gradually if discomfort stays mild.',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/physio/assessments', [
            'body_area' => 'legs',
            'symptoms' => 'sore after workout',
            'pain_level' => 2,
        ])->json('data.id');

        $response = $this->postJson('/api/physio/assessments/'.$id.'/messages', [
            'message' => 'Can I train tomorrow?',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_message.role', 'user')
            ->assertJsonPath('data.assistant_message.role', 'assistant');

        $this->assertSame(4, PhysioMessage::query()->count());
    }

    public function test_empty_and_oversized_follow_up_rejected(): void
    {
        $this->fakePhysioSuccess();
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/physio/assessments', [
            'body_area' => 'knee',
            'symptoms' => 'mild pain',
        ])->json('data.id');

        $this->postJson('/api/physio/assessments/'.$id.'/messages', [
            'message' => '   ',
        ])->assertStatus(422);

        $this->postJson('/api/physio/assessments/'.$id.'/messages', [
            'message' => str_repeat('a', 2001),
        ])->assertStatus(422);
    }

    public function test_red_flag_sets_urgent_care(): void
    {
        $this->fakePhysioSuccess([
            'risk_level' => 'urgent',
            'requires_urgent_care' => true,
            'summary' => 'Urgent medical evaluation may be needed.',
            'professional_care' => 'Seek urgent/emergency medical care immediately.',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/physio/assessments', [
            'body_area' => 'knee',
            'symptoms' => 'Severe pain after an injury and unable to bear weight.',
            'pain_level' => 9,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.requires_urgent_care', true)
            ->assertJsonPath('data.risk_level', 'urgent');
    }

    public function test_delete_own_assessment(): void
    {
        $this->fakePhysioSuccess();
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/physio/assessments', [
            'body_area' => 'shoulder',
            'symptoms' => 'tight after pressing',
        ])->json('data.id');

        $this->deleteJson('/api/physio/assessments/'.$id)->assertOk();
        $this->assertDatabaseMissing('physio_assessments', ['id' => $id]);
        $this->assertSame(0, PhysioMessage::query()->count());
    }

    public function test_physio_service_failure_returns_friendly_error(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false], 500),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/physio/assessments', [
            'body_area' => 'knee',
            'symptoms' => 'mild pain',
        ])->assertStatus(502);

        $this->assertSame(0, PhysioAssessment::query()->count());
    }

    public function test_list_assessments(): void
    {
        $this->fakePhysioSuccess();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/physio/assessments', [
            'body_area' => 'ankle',
            'symptoms' => 'sore after running',
            'pain_level' => 2,
        ])->assertCreated();

        $this->getJson('/api/physio/assessments')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }
}
