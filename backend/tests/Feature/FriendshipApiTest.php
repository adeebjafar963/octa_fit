<?php

namespace Tests\Feature;

use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\Post;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendshipApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
    }

    public function test_search_requires_auth_and_excludes_self(): void
    {
        $a = User::factory()->create(['name' => 'Adeeb Jafar']);
        User::factory()->create(['name' => 'Adeeb Trainer']);
        Sanctum::actingAs($a);

        $this->getJson('/api/friends/search?q=Adeeb')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonMissing(['id' => $a->id]);
    }

    public function test_friend_request_accept_and_feed_visibility(): void
    {
        $a = User::factory()->create(['name' => 'Alice Athlete']);
        $b = User::factory()->create(['name' => 'Bob Lifter']);

        Sanctum::actingAs($a);
        $reqId = $this->postJson('/api/friends/requests', ['user_id' => $b->id])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/friends/requests', ['user_id' => $b->id])->assertStatus(422);
        $this->postJson('/api/friends/requests', ['user_id' => $a->id])->assertStatus(422);

        Sanctum::actingAs($a);
        $postId = $this->postJson('/api/community/posts', [
            'content' => 'Private friend workout',
        ])->json('data.id');

        Sanctum::actingAs($b);
        $this->getJson('/api/community/posts')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->postJson('/api/friends/requests/'.$reqId.'/accept')->assertOk();

        $this->getJson('/api/community/posts')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonFragment(['id' => $postId]);

        Sanctum::actingAs($a);
        $this->getJson('/api/friends')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->deleteJson('/api/friends/'.$b->id)->assertOk();

        Sanctum::actingAs($b);
        $this->getJson('/api/community/posts')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_story_visibility_friends_only(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        Sanctum::actingAs($a);
        $this->postJson('/api/community/stories', [
            'media_url' => 'https://cdn.octafit.test/a.jpg',
        ])->assertCreated();

        Sanctum::actingAs($b);
        $this->getJson('/api/community/stories')
            ->assertOk()
            ->assertJsonPath('data.items', []);

        Friendship::query()->create([
            'user_id' => min($a->id, $b->id),
            'friend_id' => max($a->id, $b->id),
        ]);

        $this->getJson('/api/community/stories')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_reject_request(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        Sanctum::actingAs($a);
        $reqId = $this->postJson('/api/friends/requests', ['user_id' => $b->id])->json('data.id');

        Sanctum::actingAs($b);
        $this->postJson('/api/friends/requests/'.$reqId.'/reject')->assertOk();
        $this->assertDatabaseHas('friend_requests', [
            'id' => $reqId,
            'status' => FriendRequest::STATUS_REJECTED,
        ]);
        $this->assertSame(0, Friendship::query()->count());
    }

    public function test_duplicate_friendship_prevention(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        Sanctum::actingAs($a);
        $reqId = $this->postJson('/api/friends/requests', ['user_id' => $b->id])->json('data.id');
        Sanctum::actingAs($b);
        $this->postJson('/api/friends/requests/'.$reqId.'/accept')->assertOk();

        $this->assertSame(1, Friendship::query()->count());
        Sanctum::actingAs($b);
        $this->postJson('/api/friends/requests', ['user_id' => $a->id])->assertStatus(422);
    }
}
