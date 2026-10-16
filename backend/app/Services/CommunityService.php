<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Story;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CommunityService
{
    public const STORY_TTL_HOURS = 24;

    public function __construct(
        private readonly FriendshipService $friendshipService,
    ) {}

    public function listPosts(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);
        $authorIds = $this->friendshipService->visibleAuthorIds($user);

        return Post::query()
            ->published()
            ->whereIn('user_id', $authorIds)
            ->with(['user.profile'])
            ->withCount(['likes', 'comments'])
            ->withExists([
                'likes as is_liked' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findPost(User $user, int $postId): ?Post
    {
        $authorIds = $this->friendshipService->visibleAuthorIds($user);

        return Post::query()
            ->published()
            ->where('id', $postId)
            ->whereIn('user_id', $authorIds)
            ->with(['user.profile'])
            ->withCount(['likes', 'comments'])
            ->withExists([
                'likes as is_liked' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->first();
    }

    public function createPost(User $user, ?string $content, ?string $imageUrl = null): Post
    {
        $content = trim((string) $content);
        $imageUrl = $imageUrl !== null ? trim($imageUrl) : null;
        if ($imageUrl === '') {
            $imageUrl = null;
        }

        if ($content === '' && $imageUrl === null) {
            throw ValidationException::withMessages([
                'content' => ['Post content or image is required.'],
            ]);
        }

        $post = Post::query()->create([
            'user_id' => $user->id,
            'content' => $content === '' ? ' ' : $content,
            'image_url' => $imageUrl,
            'is_published' => true,
        ]);

        return $this->findPost($user, $post->id) ?? $post;
    }

    public function updatePost(Post $post, User $user, string $content, ?string $imageUrl = null): Post
    {
        $content = trim($content);
        if ($content === '' && ($imageUrl === null || trim((string) $imageUrl) === '')) {
            throw ValidationException::withMessages([
                'content' => ['Post content or image is required.'],
            ]);
        }

        $post->content = $content === '' ? ' ' : $content;
        $post->image_url = $imageUrl !== null && trim($imageUrl) !== '' ? trim($imageUrl) : null;
        $post->save();

        return $this->findPost($user, $post->id) ?? $post->fresh(['user.profile']);
    }

    public function deletePost(Post $post): void
    {
        $post->delete();
    }

    /**
     * @return array{liked: bool, like_count: int}
     */
    public function likePost(User $user, Post $post): array
    {
        PostLike::query()->firstOrCreate([
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        return [
            'liked' => true,
            'like_count' => $post->likes()->count(),
        ];
    }

    /**
     * @return array{liked: bool, like_count: int}
     */
    public function unlikePost(User $user, Post $post): array
    {
        PostLike::query()
            ->where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->delete();

        return [
            'liked' => false,
            'like_count' => $post->likes()->count(),
        ];
    }

    public function listComments(Post $post, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return Comment::query()
            ->where('post_id', $post->id)
            ->with(['user.profile'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function createComment(User $user, Post $post, string $content): Comment
    {
        $content = trim($content);
        if ($content === '') {
            throw ValidationException::withMessages([
                'content' => ['Comment content is required.'],
            ]);
        }

        $comment = Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => $content,
        ]);

        return $comment->load(['user.profile']);
    }

    public function deleteComment(Comment $comment): void
    {
        $comment->delete();
    }

    public function findPublishedPostOrFail(User $viewer, int $postId): Post
    {
        $authorIds = $this->friendshipService->visibleAuthorIds($viewer);

        $post = Post::query()
            ->published()
            ->where('id', $postId)
            ->whereIn('user_id', $authorIds)
            ->first();

        if ($post === null) {
            abort(404, 'Post not found');
        }

        return $post;
    }

    /**
     * Active stories for viewer + friends only (server-side expiry).
     * One latest story per user.
     *
     * @return Collection<int, Story>
     */
    public function listActiveStories(User $viewer): Collection
    {
        $authorIds = $this->friendshipService->visibleAuthorIds($viewer);

        return Story::query()
            ->active()
            ->whereIn('user_id', $authorIds)
            ->with(['user.profile'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->unique('user_id')
            ->values();
    }

    public function createStory(User $user, string $mediaUrl, ?string $caption = null): Story
    {
        $mediaUrl = trim($mediaUrl);
        if ($mediaUrl === '') {
            throw ValidationException::withMessages([
                'media_url' => ['Story media is required.'],
            ]);
        }

        $caption = $caption !== null ? trim($caption) : null;
        if ($caption === '') {
            $caption = null;
        }

        $story = Story::query()->create([
            'user_id' => $user->id,
            'media_url' => $mediaUrl,
            'caption' => $caption,
            'expires_at' => now()->addHours(self::STORY_TTL_HOURS),
            'is_active' => true,
        ]);

        return $story->load(['user.profile']);
    }

    public function deleteStory(Story $story): void
    {
        $story->is_active = false;
        $story->save();
        $story->delete();
    }

    /**
     * @return array{url: string}
     */
    public function storeMedia(User $user, UploadedFile $file): array
    {
        $path = $file->store('community/'.$user->id, 'public');
        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => ['Unable to store media file.'],
            ]);
        }

        return [
            'url' => Storage::disk('public')->url($path),
        ];
    }
}
