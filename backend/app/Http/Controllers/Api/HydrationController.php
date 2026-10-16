<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hydration\StoreWaterIntakeRequest;
use App\Http\Requests\Hydration\UpdateHydrationGoalRequest;
use App\Services\HydrationGoalService;
use App\Services\HydrationService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HydrationController extends Controller
{
    public function __construct(
        private readonly HydrationService $hydration,
        private readonly HydrationGoalService $goals,
    ) {}

    public function today(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $timezone = $request->query('timezone');

        return ApiResponse::success(
            data: $this->hydration->today($user, is_string($timezone) ? $timezone : null),
            message: 'Hydration today',
        );
    }

    public function history(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $days = (int) $request->query('days', 14);
        $timezone = $request->query('timezone');

        return ApiResponse::success(
            data: $this->hydration->history($user, $days, is_string($timezone) ? $timezone : null),
            message: 'Hydration history',
        );
    }

    public function store(StoreWaterIntakeRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $consumedAt = $request->validated('consumed_at');
        $timezone = $request->validated('timezone');

        try {
            $intake = $this->hydration->addIntake(
                $user,
                (int) $request->validated('amount_ml'),
                $consumedAt ? Carbon::parse($consumedAt) : null,
                is_string($timezone) ? $timezone : null,
            );
        } catch (HttpException $e) {
            return ApiResponse::error(message: $e->getMessage(), status: $e->getStatusCode());
        }

        $summary = $this->hydration->today($user, is_string($timezone) ? $timezone : null);

        return ApiResponse::success(
            data: [
                'intake' => [
                    'id' => $intake->id,
                    'amount_ml' => $intake->amount_ml,
                    'consumed_at' => $intake->consumed_at?->toIso8601String(),
                ],
                'today' => $summary,
            ],
            message: 'Water intake recorded',
            status: 201,
        );
    }

    public function destroy(Request $request, int $intake): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $timezone = $request->query('timezone');

        try {
            $this->hydration->deleteIntake($user, $intake);
        } catch (HttpException $e) {
            return ApiResponse::error(message: $e->getMessage(), status: $e->getStatusCode());
        }

        return ApiResponse::success(
            data: [
                'today' => $this->hydration->today($user, is_string($timezone) ? $timezone : null),
            ],
            message: 'Water intake deleted',
        );
    }

    public function goal(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->loadMissing('profile');

        return ApiResponse::success(
            data: [
                'goal_ml' => $this->goals->effectiveGoal($user),
                'recommended_goal_ml' => $this->goals->recommend($user),
                'goal_is_custom' => (bool) ($user->profile?->water_goal_is_custom),
            ],
            message: 'Hydration goal',
        );
    }

    public function updateGoal(UpdateHydrationGoalRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($request->boolean('reset_to_recommended')) {
            $this->goals->resetToRecommended($user);
        } else {
            $this->goals->setCustomGoal($user, (int) $request->validated('goal_ml'));
        }

        $timezone = $request->input('timezone');

        return ApiResponse::success(
            data: [
                'goal_ml' => $this->goals->effectiveGoal($user->fresh(['profile'])),
                'recommended_goal_ml' => $this->goals->recommend($user),
                'goal_is_custom' => (bool) ($user->fresh(['profile'])->profile?->water_goal_is_custom),
                'today' => $this->hydration->today($user->fresh(['profile']), is_string($timezone) ? $timezone : null),
            ],
            message: 'Hydration goal updated',
        );
    }
}
