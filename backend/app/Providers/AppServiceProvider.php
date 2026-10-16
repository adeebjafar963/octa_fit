<?php

namespace App\Providers;

use App\Models\CoachConversation;
use App\Models\CoachMemory;
use App\Models\Comment;
use App\Models\Exercise;
use App\Models\PhysioAssessment;
use App\Models\Post;
use App\Models\Story;
use App\Models\TrainingCategory;
use App\Models\User;
use App\Policies\CoachConversationPolicy;
use App\Policies\CoachMemoryPolicy;
use App\Policies\CommentPolicy;
use App\Policies\PhysioAssessmentPolicy;
use App\Policies\PostPolicy;
use App\Policies\StoryPolicy;
use App\Policies\TrainingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(TrainingCategory::class, TrainingPolicy::class);
        Gate::policy(Exercise::class, TrainingPolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Story::class, StoryPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(CoachConversation::class, CoachConversationPolicy::class);
        Gate::policy(CoachMemory::class, CoachMemoryPolicy::class);
        Gate::policy(PhysioAssessment::class, PhysioAssessmentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        RateLimiter::for('coach-messages', function (Request $request) {
            // Graduation demo recording needs headroom for the 25-script run.
            $perMinute = config('services.ai.coach_demo_mode') ? 60 : 20;

            return Limit::perMinute($perMinute)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('physio-assessments', function (Request $request) {
            $perMinute = config('services.ai.physio_demo_mode') ? 60 : 10;

            return Limit::perMinute($perMinute)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('physio-messages', function (Request $request) {
            return Limit::perMinute(20)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('motion-analyze', function (Request $request) {
            return Limit::perMinute(6)->by((string) ($request->user()?->id ?? $request->ip()));
        });
    }
}
