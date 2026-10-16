<?php

namespace App\Services\Coach;

/**
 * Validate generated plans against explicit user constraints.
 */
class CoachRequestResultValidator
{
    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    public function validateTrainingPlan(array $intent, array $plan): array
    {
        $errors = [];

        if (($intent['discipline_explicit'] ?? false) === true) {
            $want = (string) ($intent['discipline'] ?? '');
            $got = (string) ($plan['discipline'] ?? '');
            if ($want !== '' && $got !== '' && $want !== $got) {
                $errors[] = "discipline_mismatch:want_{$want}_got_{$got}";
            }
        }

        if (($intent['days_explicit'] ?? false) === true) {
            $want = (int) ($intent['days_per_week'] ?? 0);
            $got = (int) ($plan['days_per_week'] ?? 0);
            $requested = (int) ($plan['requested_days_per_week'] ?? $want);
            if ($want > 0 && $got > 0 && abs($got - $want) > 0 && $requested !== $want) {
                $errors[] = "days_mismatch:want_{$want}_got_{$got}";
            }
            // Soft: allow fewer sessions only if slots could not be filled, but flag if wildly off.
            if ($want > 0 && $got > 0 && $got < max(2, $want - 1)) {
                $errors[] = "days_severely_short:want_{$want}_got_{$got}";
            }
        }

        if (($intent['duration_explicit'] ?? false) === true) {
            $want = (int) ($intent['session_duration_minutes'] ?? 0);
            $got = (int) ($plan['requested_session_duration_minutes']
                ?? $plan['estimated_session_duration_minutes']
                ?? 0);
            if ($want >= 90 && $got > 0 && $got < (int) round($want * 0.7)) {
                $errors[] = "duration_too_short:want_{$want}_got_{$got}";
            }
        }

        // Explicit gym must not silently become home-only equipment mode.
        $wantEquip = (string) ($intent['equipment_mode'] ?? '');
        $gotEquip = (string) ($plan['equipment_mode'] ?? '');
        if ($wantEquip === 'gym' && $gotEquip !== '' && $gotEquip === 'home') {
            $errors[] = 'equipment_mismatch:want_gym_got_home';
        }

        return $errors;
    }
}
