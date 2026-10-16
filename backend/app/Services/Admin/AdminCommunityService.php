<?php

namespace App\Services\Admin;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminCommunityService
{
    public function listPosts(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return Post::query()
            ->with('user')
            ->withCount(['likes', 'comments'])
            ->when($search, function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';
                $q->where('content', 'like', $like);
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?Post
    {
        return Post::query()
            ->with('user')
            ->withCount(['likes', 'comments'])
            ->find($id);
    }

    public function deletePost(Post $post): void
    {
        $post->delete();
    }
}
