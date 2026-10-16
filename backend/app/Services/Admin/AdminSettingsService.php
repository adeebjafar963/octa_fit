<?php

namespace App\Services\Admin;

use App\Models\Setting;
use RuntimeException;

class AdminSettingsService
{
    /** Safe, non-secret settings editable by admins. */
    public const ALLOWED_KEYS = [
        'app_name',
        'default_locale',
        'support_text',
        'feature_coach_enabled',
        'feature_physio_enabled',
        'feature_motion_enabled',
    ];

    /**
     * @return list<array{key: string, value: string|null, group: string}>
     */
    public function listSafeSettings(): array
    {
        $this->ensureDefaults();

        return Setting::query()
            ->whereIn('key', self::ALLOWED_KEYS)
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->map(fn (Setting $s) => [
                'key' => $s->key,
                'value' => $s->value,
                'group' => $s->group,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string|null>  $updates
     * @return list<array{key: string, value: string|null, group: string}>
     */
    public function updateSafeSettings(array $updates): array
    {
        foreach ($updates as $key => $value) {
            if (! in_array($key, self::ALLOWED_KEYS, true)) {
                throw new RuntimeException('Setting key is not allowed: '.$key);
            }

            if ($key === 'default_locale' && $value !== null && ! in_array($value, ['en', 'ar'], true)) {
                throw new RuntimeException('default_locale must be en or ar.');
            }

            if (str_starts_with($key, 'feature_') && $value !== null && ! in_array($value, ['0', '1', 'true', 'false'], true)) {
                throw new RuntimeException('Feature toggles must be boolean-like (0/1).');
            }

            $normalized = $value;
            if (str_starts_with($key, 'feature_')) {
                $normalized = in_array((string) $value, ['1', 'true'], true) ? '1' : '0';
            }

            Setting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $normalized,
                    'group' => str_starts_with($key, 'feature_') ? 'features' : 'general',
                ],
            );
        }

        return $this->listSafeSettings();
    }

    private function ensureDefaults(): void
    {
        $defaults = [
            'app_name' => ['OCTA FIT', 'general'],
            'default_locale' => ['en', 'general'],
            'support_text' => ['Contact support via your university supervisor.', 'general'],
            'feature_coach_enabled' => ['1', 'features'],
            'feature_physio_enabled' => ['1', 'features'],
            'feature_motion_enabled' => ['1', 'features'],
        ];

        foreach ($defaults as $key => [$value, $group]) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group],
            );
        }
    }
}
