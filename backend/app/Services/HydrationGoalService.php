<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;

/**
 * Wellness hydration target (NOT a medical prescription).
 *
 * Formula (explicit):
 *   base_ml = weight_kg * 35
 *   activity multiplier:
 *     sedentary=1.00, light=1.10, moderate=1.20, active=1.30, very_active=1.40
 *   training bonus = min(training_frequency, 7) * 100 ml
 *   result = clamp(base * multiplier + bonus, 1500, 4000)
 *
 * Missing weight → default 2500 ml.
 */
class HydrationGoalService
{
    public const DEFAULT_GOAL_ML = 2500;

    public const MIN_GOAL_ML = 1500;

    public const MAX_GOAL_ML = 4000;

    public function recommend(User $user): int
    {
        $user->loadMissing('profile');
        $p = $user->profile;
        if ($p === null) {
            return self::DEFAULT_GOAL_ML;
        }

        $weight = $p->weight_kg !== null ? (float) $p->weight_kg : null;
        if ($weight === null || $weight < 30 || $weight > 300) {
            return self::DEFAULT_GOAL_ML;
        }

        $base = (int) round($weight * 35);
        $multiplier = $this->activityMultiplier($p->activity_level);
        $bonus = min(max((int) ($p->training_frequency ?? 0), 0), 7) * 100;
        $goal = (int) round($base * $multiplier) + $bonus;

        return max(self::MIN_GOAL_ML, min(self::MAX_GOAL_ML, $goal));
    }

    /**
     * Effective goal: custom user goal if set, otherwise recommendation.
     */
    public function effectiveGoal(User $user): int
    {
        $user->loadMissing('profile');
        $p = $user->profile;
        if ($p !== null && $p->water_goal_is_custom && $p->water_goal_ml !== null) {
            return max(self::MIN_GOAL_ML, min(self::MAX_GOAL_ML, (int) $p->water_goal_ml));
        }

        $recommended = $this->recommend($user);
        // Persist soft default when empty so Home has a stable goal without marking custom.
        if ($p !== null && $p->water_goal_ml === null) {
            $p->water_goal_ml = $recommended;
            $p->water_goal_is_custom = false;
            $p->save();
        }

        return $recommended;
    }

    public function setCustomGoal(User $user, int $goalMl): Profile
    {
        $goalMl = max(self::MIN_GOAL_ML, min(self::MAX_GOAL_ML, $goalMl));
        $profile = $user->profile ?? Profile::query()->create([
            'user_id' => $user->id,
            'locale' => 'en',
        ]);
        $profile->water_goal_ml = $goalMl;
        $profile->water_goal_is_custom = true;
        $profile->save();

        return $profile->fresh();
    }

    public function resetToRecommended(User $user): Profile
    {
        $profile = $user->profile ?? Profile::query()->create([
            'user_id' => $user->id,
            'locale' => 'en',
        ]);
        $recommended = $this->recommend($user);
        $profile->water_goal_ml = $recommended;
        $profile->water_goal_is_custom = false;
        $profile->save();

        return $profile->fresh();
    }

    private function activityMultiplier(?string $level): float
    {
        return match ($level) {
            'sedentary' => 1.0,
            'light', 'lightly_active' => 1.1,
            'moderate', 'moderately_active' => 1.2,
            'active' => 1.3,
            'very_active' => 1.4,
            default => 1.1,
        };
    }
}
