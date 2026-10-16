<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\ExerciseVideo;
use Illuminate\Database\Seeder;

/**
 * Attaches the graduation-demo MMA Intermediate media (user-provided GIFs)
 * to existing Striking / Kicks & Knees exercises. Does not create exercises.
 *
 * Correct mapping:
 * - IMAGE 7 → Rear Uppercut (Striking, Intermediate)
 * - IMAGE 8 → Side Kick (Kicks & Knees, Intermediate)
 */
class MmaDemoMediaSeeder extends Seeder
{
    /**
     * Paths under public/exercises (served directly).
     *
     * @var array<string, array{path: string, media_type: string}>
     */
    private const MEDIA = [
        // Intermediate Striking — Rear Uppercut (IMAGE 7)
        'mma-striking-rear-uppercut' => [
            'path' => 'exercises/mma/striking/mma_striking_rear_uppercut.gif',
            'media_type' => 'gif',
        ],
        // Intermediate Kicks & Knees — Side Kick (IMAGE 8)
        'mma-kicks-side-kick' => [
            'path' => 'exercises/mma/kicks/mma_kicks_side_kick.gif',
            'media_type' => 'gif',
        ],
    ];

    /**
     * Previously incorrect demo targets — restore placeholders so IMAGE 7/8
     * are not left attached to the wrong exercises.
     *
     * @var list<string>
     */
    private const CLEAR_INCORRECT = [
        'mma-striking-lead-hook',
        'mma-kicks-clinch-knee-strikes',
    ];

    private const PLACEHOLDER_URL = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4';

    public function run(): void
    {
        $this->clearIncorrectAssociations();

        foreach (self::MEDIA as $slug => $meta) {
            $exercise = Exercise::query()->where('slug', $slug)->first();
            if ($exercise === null) {
                $this->command?->warn("Skipping missing exercise slug: {$slug}");

                continue;
            }

            if ($exercise->difficulty !== 'intermediate') {
                $this->command?->warn("Skipping {$slug}: expected intermediate, got {$exercise->difficulty}");

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
                    'duration_seconds' => 2,
                    'is_placeholder' => false,
                    'is_active' => true,
                ],
            );

            $this->command?->info("Linked {$slug} → {$publicUrl}");
        }
    }

    private function clearIncorrectAssociations(): void
    {
        foreach (self::CLEAR_INCORRECT as $slug) {
            $exercise = Exercise::query()->where('slug', $slug)->first();
            if ($exercise === null) {
                continue;
            }

            $video = ExerciseVideo::query()
                ->where('exercise_id', $exercise->id)
                ->where('sort_order', 1)
                ->first();

            if ($video === null) {
                continue;
            }

            // Only clear if this row still points at the MMA demo GIFs.
            if (
                ! str_contains((string) $video->video_url, '/exercises/mma/')
                && ! str_contains((string) $video->video_url, 'mma_striking_')
                && ! str_contains((string) $video->video_url, 'mma_kicks_')
            ) {
                continue;
            }

            $video->update([
                'title_en' => $exercise->name_en.' — Demo Placeholder',
                'title_ar' => $exercise->name_ar.' — فيديو توضيحي',
                'video_url' => self::PLACEHOLDER_URL,
                'media_type' => 'video',
                'thumbnail_url' => null,
                'duration_seconds' => 15,
                'is_placeholder' => true,
                'is_active' => true,
            ]);

            $this->command?->info("Restored placeholder for incorrect mapping: {$slug}");
        }
    }
}
