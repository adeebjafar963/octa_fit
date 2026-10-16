<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Admin\AdminCommunityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommunityController extends Controller
{
    public function __construct(
        private readonly AdminCommunityService $communityService,
    ) {}

    public function posts(Request $request): JsonResponse
    {
        $paginator = $this->communityService->listPosts(
            $request->query('search'),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => collect($paginator->items())->map(fn (Post $p) => [
                    'id' => $p->id,
                    'content' => $p->content,
                    'is_published' => (bool) $p->is_published,
                    'like_count' => $p->likes_count ?? 0,
                    'comment_count' => $p->comments_count ?? 0,
                    'created_at' => optional($p->created_at)?->toIso8601String(),
                    'author' => $p->user
                        ? ['id' => $p->user->id, 'name' => $p->user->name]
                        : null,
                ]),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Posts retrieved',
        );
    }

    public function destroyPost(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        $this->communityService->deletePost($post);

        return ApiResponse::success(message: 'Post deleted');
    }
}
