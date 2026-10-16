<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production community has NO demo content.
 * Removes legacy demo users' community rows and known E2E/seed posts.
 * Keeps real registered users and the demo login account (without posts).
 */
class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        $legacyEmails = [
            'sara@octafit.test',
            'omar@octafit.test',
            'layla@octafit.test',
        ];

        $legacyUsers = User::query()->whereIn('email', $legacyEmails)->get();
        $legacyIds = $legacyUsers->pluck('id');

        $demoUser = User::query()->where('email', 'demo@octafit.test')->first();
        if ($demoUser !== null) {
            $legacyIds = $legacyIds->push($demoUser->id)->unique();
        }

        if ($legacyIds->isNotEmpty()) {
            $postIds = Post::query()->whereIn('user_id', $legacyIds)->pluck('id');
            if ($postIds->isNotEmpty()) {
                PostLike::query()->whereIn('post_id', $postIds)->delete();
                Comment::query()->whereIn('post_id', $postIds)->delete();
                Post::query()->whereIn('id', $postIds)->delete();
            }
            Story::query()->whereIn('user_id', $legacyIds)->delete();
        }

        // Known seed / E2E content strings (any author)
        $demoContents = [
            'Finished my first full-body workout this week%',
            'Rest day reminder: recovery is part of training%',
            'Hydration check: finished 2 liters%',
            'Phase11 E2E post%',
            'Motivation: you do not need a perfect plan%',
        ];

        foreach ($demoContents as $pattern) {
            $ids = Post::query()->where('content', 'like', $pattern)->pluck('id');
            if ($ids->isEmpty()) {
                continue;
            }
            PostLike::query()->whereIn('post_id', $ids)->delete();
            Comment::query()->whereIn('post_id', $ids)->delete();
            Post::query()->whereIn('id', $ids)->delete();
        }

        foreach ($legacyUsers as $user) {
            $user->tokens()->delete();
            $user->profile()?->delete();
            $user->delete();
        }
    }
}
