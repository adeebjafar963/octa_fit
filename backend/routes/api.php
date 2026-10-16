<?php

use App\Http\Controllers\Api\Admin\AdminCommunityController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminKnowledgeController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminSettingsController;
use App\Http\Controllers\Api\Admin\AdminStoreController;
use App\Http\Controllers\Api\Admin\AdminTrainingController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AiHubController;
use App\Http\Controllers\Api\CoachController;
use App\Http\Controllers\Api\CoachMemoryController;
use App\Http\Controllers\Api\FeatureFlagsController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\FriendshipController;
use App\Http\Controllers\Api\CoachDiagnosticsController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HydrationController;
use App\Http\Controllers\Api\MotionController;
use App\Http\Controllers\Api\PhysioController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\TrainingController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);
Route::get('/coach/diagnostics', CoachDiagnosticsController::class);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::get('/profile/completion', [ProfileController::class, 'completion']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);
    Route::patch('/profile/onboarding', [ProfileController::class, 'update']);

    Route::get('/training/disciplines', [TrainingController::class, 'disciplines']);
    Route::get('/training/levels', [TrainingController::class, 'levels']);
    Route::get('/training/categories', [TrainingController::class, 'categories']);
    Route::get('/training/categories/{category}', [TrainingController::class, 'showCategory']);
    Route::get('/training/programs', [TrainingController::class, 'programs']);
    Route::get('/training/programs/{program}', [TrainingController::class, 'showProgram']);
    Route::get('/training/exercises', [TrainingController::class, 'exercises']);
    Route::get('/training/exercises/{exercise}', [TrainingController::class, 'showExercise']);
    Route::get('/training/bodybuilding', [TrainingController::class, 'bodybuilding']);
    Route::get('/training/mma', [TrainingController::class, 'mma']);
    Route::get('/training/home-calisthenics', [TrainingController::class, 'homeCalisthenics']);

    Route::get('/workouts/sessions', [TrainingController::class, 'sessions']);
    Route::post('/workouts/sessions', [TrainingController::class, 'startSession']);
    Route::get('/workouts/sessions/{session}', [TrainingController::class, 'showSession']);
    Route::post('/workouts/sessions/{session}/pause', [TrainingController::class, 'pauseSession']);
    Route::post('/workouts/sessions/{session}/resume', [TrainingController::class, 'resumeSession']);
    Route::post('/workouts/sessions/{session}/sets', [TrainingController::class, 'completeSet']);
    Route::post('/workouts/sessions/{session}/complete', [TrainingController::class, 'completeSession']);
    Route::post('/workouts/sessions/{session}/cancel', [TrainingController::class, 'cancelSession']);

    Route::get('/store/categories', [StoreController::class, 'categories']);
    Route::get('/store/products', [StoreController::class, 'products']);
    Route::get('/store/products/{product}', [StoreController::class, 'showProduct']);

    Route::get('/store/cart', [StoreController::class, 'cart']);
    Route::post('/store/cart/items', [StoreController::class, 'addCartItem']);
    Route::put('/store/cart/items/{cartItem}', [StoreController::class, 'updateCartItem']);
    Route::delete('/store/cart/items/{cartItem}', [StoreController::class, 'removeCartItem']);
    Route::delete('/store/cart', [StoreController::class, 'clearCart']);

    Route::post('/store/orders', [StoreController::class, 'checkout']);
    Route::get('/store/orders', [StoreController::class, 'orders']);
    Route::get('/store/orders/{order}', [StoreController::class, 'showOrder']);

    Route::get('/community/posts', [CommunityController::class, 'index']);
    Route::post('/community/posts', [CommunityController::class, 'store']);
    Route::get('/community/posts/{post}', [CommunityController::class, 'show']);
    Route::put('/community/posts/{post}', [CommunityController::class, 'update']);
    Route::delete('/community/posts/{post}', [CommunityController::class, 'destroy']);
    Route::post('/community/posts/{post}/like', [CommunityController::class, 'like']);
    Route::delete('/community/posts/{post}/like', [CommunityController::class, 'unlike']);
    Route::get('/community/posts/{post}/comments', [CommunityController::class, 'comments']);
    Route::post('/community/posts/{post}/comments', [CommunityController::class, 'storeComment']);
    Route::delete('/community/comments/{comment}', [CommunityController::class, 'destroyComment']);
    Route::get('/community/stories', [CommunityController::class, 'stories']);
    Route::post('/community/stories', [CommunityController::class, 'storeStory']);
    Route::delete('/community/stories/{story}', [CommunityController::class, 'destroyStory']);
    Route::post('/community/media', [CommunityController::class, 'uploadMedia'])
        ->middleware('throttle:30,1');

    Route::get('/friends/search', [FriendshipController::class, 'search']);
    Route::get('/friends', [FriendshipController::class, 'friends']);
    Route::get('/friends/requests', [FriendshipController::class, 'incoming']);
    Route::post('/friends/requests', [FriendshipController::class, 'send']);
    Route::post('/friends/requests/{friendRequest}/accept', [FriendshipController::class, 'accept']);
    Route::post('/friends/requests/{friendRequest}/reject', [FriendshipController::class, 'reject']);
    Route::delete('/friends/{user}', [FriendshipController::class, 'remove']);
    Route::get('/users/{user}/public', [FriendshipController::class, 'showUser']);

    Route::get('/features', [FeatureFlagsController::class, 'index']);

    Route::get('/hydration/today', [HydrationController::class, 'today']);
    Route::get('/hydration/history', [HydrationController::class, 'history']);
    Route::get('/hydration/goal', [HydrationController::class, 'goal']);
    Route::put('/hydration/goal', [HydrationController::class, 'updateGoal']);
    Route::post('/hydration/intakes', [HydrationController::class, 'store']);
    Route::delete('/hydration/intakes/{intake}', [HydrationController::class, 'destroy']);

    Route::get('/ai/hub', [AiHubController::class, 'hub']);
    Route::post('/ai/route', [AiHubController::class, 'route'])
        ->middleware('throttle:60,1');

    Route::middleware('feature:coach')->group(function () {
        Route::get('/coach/conversations', [CoachController::class, 'index']);
        Route::post('/coach/conversations', [CoachController::class, 'store']);
        Route::get('/coach/conversations/{conversation}', [CoachController::class, 'show']);
        Route::delete('/coach/conversations/{conversation}', [CoachController::class, 'destroy']);
        Route::post('/coach/conversations/{conversation}/messages', [CoachController::class, 'sendMessage'])
            ->middleware('throttle:coach-messages');

        // Phase 11H — Coach memory controls
        Route::get('/coach/memories', [CoachMemoryController::class, 'index']);
        Route::post('/coach/memories/{memory}/deactivate', [CoachMemoryController::class, 'deactivate']);
        Route::delete('/coach/memories/{memory}', [CoachMemoryController::class, 'destroy']);
        Route::delete('/coach/memories', [CoachMemoryController::class, 'clear']);
    });

    Route::middleware('feature:physio')->group(function () {
        Route::get('/physio/assessments', [PhysioController::class, 'index']);
        Route::post('/physio/assessments', [PhysioController::class, 'store'])
            ->middleware('throttle:physio-assessments');
        Route::get('/physio/assessments/{assessment}', [PhysioController::class, 'show']);
        Route::delete('/physio/assessments/{assessment}', [PhysioController::class, 'destroy']);
        Route::post('/physio/assessments/{assessment}/messages', [PhysioController::class, 'sendMessage'])
            ->middleware('throttle:physio-messages');
    });

    Route::middleware('feature:motion')->group(function () {
        Route::get('/motion/exercises', [MotionController::class, 'exercises']);
        Route::post('/motion/analyze', [MotionController::class, 'analyze'])
            ->middleware('throttle:motion-analyze');
    });
});
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::put('/users/{user}/role', [AdminUserController::class, 'updateRole']);
    Route::put('/users/{user}/status', [AdminUserController::class, 'updateStatus']);

    Route::get('/training/categories', [AdminTrainingController::class, 'categories']);
    Route::post('/training/categories', [AdminTrainingController::class, 'storeCategory']);
    Route::put('/training/categories/{category}', [AdminTrainingController::class, 'updateCategory']);
    Route::delete('/training/categories/{category}', [AdminTrainingController::class, 'destroyCategory']);
    Route::get('/training/exercises', [AdminTrainingController::class, 'exercises']);
    Route::post('/training/exercises', [AdminTrainingController::class, 'storeExercise']);
    Route::put('/training/exercises/{exercise}', [AdminTrainingController::class, 'updateExercise']);
    Route::delete('/training/exercises/{exercise}', [AdminTrainingController::class, 'destroyExercise']);
    Route::post('/training/exercises/{exercise}/videos', [AdminTrainingController::class, 'storeVideo']);
    Route::put('/training/videos/{video}', [AdminTrainingController::class, 'updateVideo']);
    Route::delete('/training/videos/{video}', [AdminTrainingController::class, 'destroyVideo']);

    Route::get('/store/categories', [AdminStoreController::class, 'categories']);
    Route::post('/store/categories', [AdminStoreController::class, 'storeCategory']);
    Route::put('/store/categories/{category}', [AdminStoreController::class, 'updateCategory']);
    Route::delete('/store/categories/{category}', [AdminStoreController::class, 'destroyCategory']);
    Route::get('/store/products', [AdminStoreController::class, 'products']);
    Route::post('/store/products', [AdminStoreController::class, 'storeProduct']);
    Route::put('/store/products/{product}', [AdminStoreController::class, 'updateProduct']);
    Route::delete('/store/products/{product}', [AdminStoreController::class, 'destroyProduct']);

    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::put('/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

    Route::get('/community/posts', [AdminCommunityController::class, 'posts']);
    Route::delete('/community/posts/{post}', [AdminCommunityController::class, 'destroyPost']);

    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);

    Route::get('/knowledge/sources', [AdminKnowledgeController::class, 'sources']);
    Route::get('/knowledge/documents', [AdminKnowledgeController::class, 'documents']);
    Route::get('/knowledge/chunks', [AdminKnowledgeController::class, 'chunks']);
});
