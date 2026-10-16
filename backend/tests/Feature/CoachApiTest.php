<?php

namespace Tests\Feature;

use App\Models\CoachConversation;
use App\Models\CoachMessage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
    }

    private function fakeCoachSuccess(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => "Building muscle needs progressive training.\n\n• Progressive overload\n• Adequate protein\n• Recovery",
                'provider' => 'mock',
                'language' => 'en',
            ], 200),
        ]);
    }

    public function test_conversations_require_auth(): void
    {
        $this->getJson('/api/coach/conversations')->assertUnauthorized();
    }

    public function test_create_list_show_delete_conversation(): void
    {
        $this->fakeCoachSuccess();
        Sanctum::actingAs(User::factory()->create());

        $create = $this->postJson('/api/coach/conversations', ['title' => 'Muscle Gain']);
        $create->assertCreated()->assertJsonPath('data.title', 'Muscle Gain');
        $id = $create->json('data.id');

        $this->getJson('/api/coach/conversations')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/coach/conversations/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->deleteJson('/api/coach/conversations/'.$id)->assertOk();
        $this->assertDatabaseMissing('coach_conversations', ['id' => $id]);
    }

    public function test_ownership_enforced(): void
    {
        $this->fakeCoachSuccess();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/coach/conversations', ['title' => 'Private'])
            ->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson('/api/coach/conversations/'.$id)->assertForbidden();
        $this->postJson('/api/coach/conversations/'.$id.'/messages', [
            'message' => 'Hello',
        ])->assertForbidden();
        $this->deleteJson('/api/coach/conversations/'.$id)->assertForbidden();
    }

    public function test_send_message_saves_user_and_assistant(): void
    {
        $this->fakeCoachSuccess();
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/coach/conversations')->json('data.id');

        $response = $this->postJson('/api/coach/conversations/'.$id.'/messages', [
            'message' => 'How can I build muscle?',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_message.role', 'user')
            ->assertJsonPath('data.assistant_message.role', 'assistant');

        $this->assertDatabaseCount('coach_messages', 2);
        $this->assertNotNull(CoachConversation::query()->find($id)?->title);
    }

    public function test_empty_and_oversized_messages_rejected(): void
    {
        $this->fakeCoachSuccess();
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/coach/conversations')->json('data.id');

        $this->postJson('/api/coach/conversations/'.$id.'/messages', [
            'message' => '   ',
        ])->assertStatus(422);

        $this->postJson('/api/coach/conversations/'.$id.'/messages', [
            'message' => str_repeat('a', 2001),
        ])->assertStatus(422);
    }

    public function test_coach_service_failure_returns_friendly_error(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false, 'message' => 'down'], 500),
        ]);

        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/coach/conversations')->json('data.id');

        $this->postJson('/api/coach/conversations/'.$id.'/messages', [
            'message' => 'Hello coach',
        ])->assertStatus(502);

        $this->assertSame(1, CoachMessage::query()->count());
    }
}
