<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;

/**
 * Determines whether a fitness profile has the minimum required fields.
 * Backend is the source of truth — Flutter must not invent completion.
 */
class ProfileCompletionService
{
    /** @var list<string> */
    public const REQUIRED_FIELDS = [
        'height_cm',
        'weight_kg',
        'fitness_level',
        'training_goal',
        'training_experience',
        'injury_status',
    ];

    /**
     * @return array{
     *   profile_complete: bool,
     *   completion_percentage: int,
     *   missing_fields: list<string>,
     *   onboarding_step: int,
     *   profile_completed_at: string|null
     * }
     */
    public function evaluate(?Profile $profile): array
    {
        if ($profile === null) {
            return [
                'profile_complete' => false,
                'completion_percentage' => 0,
                'missing_fields' => self::REQUIRED_FIELDS,
                'onboarding_step' => 0,
                'profile_completed_at' => null,
            ];
        }

        $missing = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            if (! $this->fieldFilled($profile, $field)) {
                $missing[] = $field;
            }
        }

        $requiredCount = count(self::REQUIRED_FIELDS);
        $filled = $requiredCount - count($missing);
        $percentage = (int) round(($filled / max($requiredCount, 1)) * 100);
        $complete = $missing === [];

        return [
            'profile_complete' => $complete,
            'completion_percentage' => $percentage,
            'missing_fields' => $missing,
            'onboarding_step' => (int) ($profile->onboarding_step ?? 0),
            'profile_completed_at' => $profile->profile_completed_at?->toIso8601String(),
        ];
    }

    public function isComplete(?Profile $profile): bool
    {
        return $this->evaluate($profile)['profile_complete'];
    }

    public function syncCompletionTimestamp(Profile $profile): void
    {
        $complete = $this->isComplete($profile);
        if ($complete && $profile->profile_completed_at === null) {
            $profile->profile_completed_at = now();
            $profile->save();
        } elseif (! $complete && $profile->profile_completed_at !== null) {
            // Keep completed_at if previously complete but a required field cleared —
            // re-evaluate: clear stamp so routing forces resume.
            $profile->profile_completed_at = null;
            $profile->save();
        }
    }

    private function fieldFilled(Profile $profile, string $field): bool
    {
        $value = $profile->{$field} ?? null;
        if ($value === null) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }

        return true;
    }
}
