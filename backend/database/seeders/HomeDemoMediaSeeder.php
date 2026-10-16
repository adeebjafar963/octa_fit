<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\ExerciseVideo;
use Illuminate\Database\Seeder;

/**
 * Attaches graduation-demo Home Workouts Advanced media (user-provided files)
 * to Push / Pull exercises. Creates Wide Grip Push-Up only if missing.
 */
class HomeDemoMediaSeeder extends Seeder
{
    /**
     * @var array<string, array{path: string, media_type: string}>
     */
    private const MEDIA = [
        // IMAGE 9 — Advanced Push — Wide Grip Push-Up
        'home-push-wide-grip-push-up' => [
            'path' => 'exercises/home/push/home_push_wide_grip_push_up.gif',
            'media_type' => 'gif',
        ],
        // IMAGE 10 — Advanced Push — Diamond Push-Up
        'home-push-diamond-push-up' => [
            'path' => 'exercises/home/push/home_push_diamond_push_up.gif',
            'media_type' => 'gif',
        ],
        // IMAGE 11 — Advanced Pull — Pull-Up
        'home-pull-pull-up' => [
            'path' => 'exercises/home/pull/home_pull_pull_up.jpg',
            'media_type' => 'image',
        ],
    ];

    public function run(): void
    {
        foreach (self::MEDIA as $slug => $meta) {
            $exercise = Exercise::query()->where('slug', $slug)->first();
            if ($exercise === null) {
                $this->command?->warn("Skipping missing exercise slug: {$slug}");

                continue;
            }

            if ($exercise->difficulty !== 'advanced') {
                $exercise->update(['difficulty' => 'advanced']);
                $this->command?->info("Set {$slug} difficulty → advanced");
            }

            $absolute = public_path($meta['path']);
            if (! is_file($absolute)) {
                $this->command?->error("Missing media file for {$slug}: {$absolute}");

                continue;
            }

            $publicUrl = url($meta['path']);

            ExerciseVideo::query()->updateOrCreate(
                [
                    'exercise_id' => $exercise->id,
                    'sort_order' => 1,
                ],
                [
                    'title_en' => $exercise->name_en,
                    'title_ar' => $exercise->name_ar,
                    'video_url' => $publicUrl,
                    'media_type' => $meta['media_type'],
                    'thumbnail_url' => null,
                    'duration_seconds' => $meta['media_type'] === 'gif' ? 2 : null,
                    'is_placeholder' => false,
                    'is_active' => true,
                ],
            );

            $this->command?->info("Linked {$slug} → {$publicUrl}");
        }
    }
}
