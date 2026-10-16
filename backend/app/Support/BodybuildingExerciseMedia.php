<?php

namespace App\Support;

/**
 * Local animation paths for the bodybuilding catalog.
 * Filenames follow the exercise slug. MMA and other disciplines are ignored.
 */
class BodybuildingExerciseMedia
{
    /** @var list<string> */
    private const GROUPS = [
        'full-body',
        'hamstrings',
        'shoulders',
        'forearms',
        'triceps',
        'biceps',
        'glutes',
        'calves',
        'chest',
        'back',
        'quads',
        'core',
    ];

    public static function assetForSlug(string $slug): ?string
    {
        if (! str_starts_with($slug, 'bb-')) {
            return null;
        }

        $rest = substr($slug, 3);
        foreach (self::GROUPS as $group) {
            if ($rest === $group || str_starts_with($rest, $group.'-')) {
                $folder = str_replace('-', '_', $group);
                $file = str_replace('-', '_', $slug).'.gif';

                return "assets/exercises/bodybuilding/{$folder}/{$file}";
            }
        }

        return null;
    }
}
