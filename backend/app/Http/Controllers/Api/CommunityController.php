<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommentRequest;
use App\Http\Requests\Community\StorePostRequest;
use App\Http\Requests\Community\StoreStoryRequest;
use App\Http\Requests\Community\UpdatePostRequest;
use App\Http\Requests\Community\UploadCommunityMediaRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\StoryResource;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Story;
use App\Services\CommunityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommunityController extends Controller
{
    public function __construct(
        private readonly CommunityService $communityService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->communityService->listPosts(
            $request->user(),
            (int) $request->query('per_page', 15),
        );

        return ApiResponse::success(
            data: [
                'items' => PostResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Community feed retrieved',
        );
    }

    public function show(Request $request, int $post): JsonResponse
    {
        $model = $this->communityService->findPost($request->user(), $post);
        if ($model === null) {
            return ApiResponse::error('Post not found', 404);
        }

        return ApiResponse::success(
            data: (new PostResource($model))->resolve(),
            message: 'Post retrieved',
        );
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        try {
            $post = $this->communityService->createPost(
                $request->user(),
                $request->validated('content'),
                $request->validated('image_url'),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to create post', 422, $e->errors());
        }

        return ApiResponse::success(
            data: (new PostResource($post))->resolve(),
            message: 'Post created',
            status: 201,
        );
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        if (! $post->is_published) {
            return ApiResponse::error('Post not found', 404);
        }

        try {
            $updated = $this->communityService->updatePost(
                $post,
                $request->user(),
                $request->validated('content'),
                $request->validated('image_url'),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to update post', 422, $e->errors());
        }

        return ApiResponse::success(
            data: (new PostResource($updated))->resolve(),
            message: 'Post updated',
        );
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        $this->communityService->deletePost($post);

        return ApiResponse::success(message: 'Post deleted');
    }

    public function like(Request $request, int $post): JsonResponse
    {
        $model = $this->communityService->findPublishedPostOrFail($request->user(), $post);
        $result = $this->communityService->likePost($request->user(), $model);

        return ApiResponse::success(data: $result, message: 'Post liked');
    }

    public function unlike(Request $request, int $post): JsonResponse
    {
        $model = $this->communityService->findPublishedPostOrFail($request->user(), $post);
        $result = $this->communityService->unlikePost($request->user(), $model);

        return ApiResponse::success(data: $result, message: 'Post unliked');
    }

    public function comments(Request $request, int $post): JsonResponse
    {
        $model = $this->communityService->findPublishedPostOrFail($request->user(), $post);
        $paginator = $this->communityService->listComments(
            $model,
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => CommentResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Comments retrieved',
        );
    }

    public function storeComment(StoreCommentRequest $request, int $post): JsonResponse
    {
        $model = $this->communityService->findPublishedPostOrFail($request->user(), $post);

        try {
            $comment = $this->communityService->createComment(
                $request->user(),
                $model,
                $request->validated('content'),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to create comment', 422, $e->errors());
        }

        return ApiResponse::success(
            data: (new CommentResource($comment))->resolve(),
            message: 'Comment created',
            status: 201,
        );
    }

    public function destroyComment(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);
        $this->communityService->deleteComment($comment);

        return ApiResponse::success(message: 'Comment deleted');
    }

    public function stories(Request $request): JsonResponse
    {
        $stories = $this->communityService->listActiveStories($request->user());

        return ApiResponse::success(
            data: [
                'items' => StoryResource::collection($stories)->resolve(),
            ],
            message: 'Active stories retrieved',
        );
    }

    public function storeStory(StoreStoryRequest $request): JsonResponse
    {
        try {
            $story = $this->communityService->createStory(
                $request->user(),
                $request->validated('media_url'),
                $request->validated('caption'),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to create story', 422, $e->errors());
        }

        return ApiResponse::success(
            data: (new StoryResource($story))->resolve(),
            message: 'Story created',
            status: 201,
        );
    }

    public function destroyStory(Story $story): JsonResponse
    {
        $this->authorize('delete', $story);
        $this->communityService->deleteStory($story);

        return ApiResponse::success(message: 'Story deleted');
    }

    public function uploadMedia(UploadCommunityMediaRequest $request): JsonResponse
    {
        try {
            $result = $this->communityService->storeMedia(
                $request->user(),
                $request->file('file'),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to upload media', 422, $e->errors());
        }

        return ApiResponse::success(
            data: $result,
            message: 'Media uploaded',
            status: 201,
        );
    }
}
