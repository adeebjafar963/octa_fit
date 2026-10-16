<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\ExerciseVideo;
use Illuminate\Database\Seeder;

/**
 * Attaches the graduation-demo bodybuilding media (user-provided GIFs/images)
 * to existing chest / back / shoulders exercises. Does not create exercises.
 */
class BodybuildingDemoMediaSeeder extends Seeder
{
    /**
     * Paths under public/exercises (served directly, tracked for the demo).
     *
     * @var array<string, array{path: string, media_type: string}>
     */
    private const MEDIA = [
        'bb-chest-flat-dumbbell-press' => [
            'path' => 'exercises/bodybuilding/chest/bb_chest_flat_dumbbell_press.gif',
            'media_type' => 'gif',
        ],
        'bb-chest-machine-chest-press' => [
            'path' => 'exercises/bodybuilding/chest/bb_chest_machine_chest_press.gif',
            'media_type' => 'gif',
        ],
        'bb-back-lat-pulldown' => [
            'path' => 'exercises/bodybuilding/back/bb_back_lat_pulldown.gif',
            'media_type' => 'gif',
        ],
        'bb-back-one-arm-dumbbell-row' => [
            'path' => 'exercises/bodybuilding/back/bb_back_one_arm_dumbbell_row.jpg',
            'media_type' => 'image',
        ],
        'bb-shoulders-seated-dumbbell-shoulder-press' => [
            'path' => 'exercises/bodybuilding/shoulders/bb_shoulders_seated_dumbbell_shoulder_press.gif',
            'media_type' => 'gif',
        ],
        'bb-shoulders-dumbbell-lateral-raise' => [
            'path' => 'exercises/bodybuilding/shoulders/bb_shoulders_dumbbell_lateral_raise.gif',
            'media_type' => 'gif',
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
