<?php

namespace App\Services\Coach\Nutrition;

use App\Models\Food;
use App\Models\NutritionPlan;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Phase 11E — persist nutrition plan versions (foundation for active_plan).
 */
class NutritionPlanRepository
{
    public function latestActive(User $user): ?NutritionPlan
    {
        return NutritionPlan::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public function saveNewVersion(User $user, array $plan, ?NutritionPlan $previous = null): NutritionPlan
    {
        if ($previous !== null) {
            $previous->status = 'superseded';
            $previous->save();
            $uuid = $previous->plan_uuid;
            $version = $previous->version + 1;
        } else {
            $uuid = (string) Str::uuid();
            $version = 1;
        }

        // Mark any other active plans superseded.
        NutritionPlan::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'superseded']);

        $plan['plan_id'] = $uuid;
        $plan['version'] = $version;

        return NutritionPlan::query()->create([
            'plan_uuid' => $uuid,
            'user_id' => $user->id,
            'version' => $version,
            'status' => 'active',
            'goal' => (string) ($plan['goal'] ?? 'maintenance'),
            'calorie_target' => isset($plan['calorie_target']) ? (int) $plan['calorie_target'] : null,
            'meals_per_day' => isset($plan['meals_per_day']) ? (int) $plan['meals_per_day'] : null,
            'duration_days' => isset($plan['duration_days']) ? (int) $plan['duration_days'] : null,
            'payload' => $plan,
            'assumptions' => $plan['assumptions'] ?? [],
        ]);
    }
}
