<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'user', 'display_name' => 'User']);
    }

    public function test_feed_requires_auth(): void
    {
        $this->getJson('/api/community/posts')->assertUnauthorized();
    }

    public function test_feed_starts_empty_without_demo_data(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/community/posts')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonPath('data.items', []);
    }

    public function test_feed_is_paginated_newest_first(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (range(1, 6) as $i) {
            Post::query()->create([
                'user_id' => $user->id,
                'content' => "Post {$i}",
                'is_published' => true,
                'created_at' => now()->subMinutes(6 - $i),
            ]);
        }

        $response = $this->getJson('/api/community/posts?per_page=5');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.per_page', 5)
            ->assertJsonPath('data.meta.total', 6);

        $items = $response->json('data.items');
        $this->assertGreaterThanOrEqual($items[1]['created_at'] ?? '', $items[0]['created_at'] ?? '');
    }

    public function test_user_can_create_and_view_post(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $create = $this->postJson('/api/community/posts', [
            'content' => 'My first OCTA FIT workout today',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.content', 'My first OCTA FIT workout today')
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.like_count', 0);

        $id = $create->json('data.id');
        $this->getJson('/api/community/posts/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    public function test_empty_and_too_long_posts_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/community/posts', ['content' => '   '])
            ->assertStatus(422);

        $this->postJson('/api/community/posts', ['content' => str_repeat('a', 1001)])
            ->assertStatus(422);
    }

    public function test_user_can_update_and_delete_own_post_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $postId = $this->postJson('/api/community/posts', [
            'content' => 'Original progress note',
        ])->json('data.id');

        $this->putJson('/api/community/posts/'.$postId, [
            'content' => 'Updated workout progress',
        ])->assertOk()->assertJsonPath('data.content', 'Updated workout progress');

        Sanctum::actingAs($other);
        $this->putJson('/api/community/posts/'.$postId, [
            'content' => 'Hacked',
        ])->assertForbidden();

        $this->deleteJson('/api/community/posts/'.$postId)->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson('/api/community/posts/'.$postId)->assertOk();
        $this->assertDatabaseMissing('posts', ['id' => $postId]);
    }

    public function test_like_unlike_and_duplicate_prevention(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $postId = $this->postJson('/api/community/posts', [
            'content' => 'Likeable post',
        ])->json('data.id');

        $this->postJson('/api/community/posts/'.$postId.'/like')
            ->assertOk()
            ->assertJsonPath('data.liked', true);

        $countAfterFirst = $this->postJson('/api/community/posts/'.$postId.'/like')
            ->assertOk()
            ->json('data.like_count');

        $this->assertSame(1, PostLike::query()->where('post_id', $postId)->where('user_id', $user->id)->count());

        \App\Models\Friendship::query()->create([
            'user_id' => min($user->id, $other->id),
            'friend_id' => max($user->id, $other->id),
        ]);

        Sanctum::actingAs($other);
        $this->postJson('/api/community/posts/'.$postId.'/like')
            ->assertOk()
            ->assertJsonPath('data.liked', true);

        $this->assertGreaterThan($countAfterFirst, PostLike::query()->where('post_id', $postId)->count());

        Sanctum::actingAs($user);
        $this->deleteJson('/api/community/posts/'.$postId.'/like')
            ->assertOk()
            ->assertJsonPath('data.liked', false);

        $this->deleteJson('/api/community/posts/'.$postId.'/like')
            ->assertOk()
            ->assertJsonPath('data.liked', false);

        $this->assertSame(0, PostLike::query()->where('post_id', $postId)->where('user_id', $user->id)->count());
    }

    public function test_comments_create_list_and_ownership_delete(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $postId = $this->postJson('/api/community/posts', [
            'content' => 'Post for comments',
        ])->json('data.id');

        $this->postJson('/api/community/posts/'.$postId.'/comments', [
            'content' => '   ',
        ])->assertStatus(422);

        $commentId = $this->postJson('/api/community/posts/'.$postId.'/comments', [
            'content' => 'Great progress!',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/community/posts/'.$postId.'/comments')
            ->assertOk()
            ->assertJsonPath('success', true);

        Sanctum::actingAs($other);
        $this->deleteJson('/api/community/comments/'.$commentId)->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson('/api/community/comments/'.$commentId)->assertOk();
        $this->assertDatabaseMissing('comments', ['id' => $commentId]);
    }

    public function test_deleting_post_cascades_likes_and_comments(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $postId = $this->postJson('/api/community/posts', [
            'content' => 'Temporary post for cascade test',
        ])->json('data.id');

        $this->postJson('/api/community/posts/'.$postId.'/like')->assertOk();
        $commentId = $this->postJson('/api/community/posts/'.$postId.'/comments', [
            'content' => 'Temporary comment',
        ])->json('data.id');

        $this->deleteJson('/api/community/posts/'.$postId)->assertOk();

        $this->assertDatabaseMissing('posts', ['id' => $postId]);
        $this->assertDatabaseMissing('post_likes', ['post_id' => $postId]);
        $this->assertDatabaseMissing('comments', ['id' => $commentId]);
    }

    public function test_feed_includes_like_state_without_n_plus_one_crash(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $postId = $this->postJson('/api/community/posts', [
            'content' => 'Liked in feed',
        ])->json('data.id');
        $this->postJson('/api/community/posts/'.$postId.'/like')->assertOk();

        $this->getJson('/api/community/posts')
            ->assertOk()
            ->assertJsonFragment(['is_liked' => true]);
    }

    public function test_stories_active_only_and_expiry_excluded(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $active = $this->postJson('/api/community/stories', [
            'media_url' => 'https://cdn.octafit.test/story-active.jpg',
            'caption' => 'Morning session',
        ])->assertCreated()->json('data');

        $this->assertNotNull($active['expires_at']);
        $this->assertTrue($active['is_owner']);

        Story::query()->create([
            'user_id' => $other->id,
            'media_url' => 'https://cdn.octafit.test/story-expired.jpg',
            'caption' => 'Old',
            'expires_at' => now()->subHour(),
            'is_active' => true,
        ]);

        $list = $this->getJson('/api/community/stories')->assertOk()->json('data.items');
        $ids = collect($list)->pluck('id')->all();
        $this->assertContains($active['id'], $ids);
        $this->assertCount(1, $list);
    }

    public function test_story_delete_own_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $storyId = $this->postJson('/api/community/stories', [
            'media_url' => 'https://cdn.octafit.test/mine.jpg',
        ])->json('data.id');

        Sanctum::actingAs($other);
        $this->deleteJson('/api/community/stories/'.$storyId)->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson('/api/community/stories/'.$storyId)->assertOk();
        $this->assertDatabaseMissing('stories', ['id' => $storyId]);
    }

    public function test_media_upload_returns_url(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $file = UploadedFile::fake()->create('lift.jpg', 120, 'image/jpeg');

        $this->post('/api/community/media', ['file' => $file], [
            'Accept' => 'application/json',
        ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['url']]);
    }
}
