<?php

namespace App\Services;

use App\Models\Setting;

class FeatureFlagsService
{
    public const KEY_COACH = 'feature_coach_enabled';

    public const KEY_PHYSIO = 'feature_physio_enabled';

    public const KEY_MOTION = 'feature_motion_enabled';

    public function isEnabled(string $key): bool
    {
        $value = Setting::query()->where('key', $key)->value('value');

        // Missing rows default to enabled so demos keep working before seeding.
        if ($value === null) {
            return true;
        }

        return in_array((string) $value, ['1', 'true'], true);
    }

    /**
     * @return array{coach_enabled: bool, physio_enabled: bool, motion_enabled: bool}
     */
    public function publicFlags(): array
    {
        return [
            'coach_enabled' => $this->isEnabled(self::KEY_COACH),
            'physio_enabled' => $this->isEnabled(self::KEY_PHYSIO),
            'motion_enabled' => $this->isEnabled(self::KEY_MOTION),
        ];
    }
}
