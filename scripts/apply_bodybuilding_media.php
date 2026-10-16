<?php

use App\Models\Exercise;
use App\Models\ExerciseVideo;
use App\Support\BodybuildingExerciseMedia;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$exercises = Exercise::query()
    ->where('slug', 'like', 'bb-%')
    ->orderBy('id')
    ->get();

$updated = 0;
foreach ($exercises as $exercise) {
    $asset = BodybuildingExerciseMedia::assetForSlug($exercise->slug);
    if ($asset === null) {
        fwrite(STDERR, "No asset for {$exercise->slug}\n");
        continue;
    }

    ExerciseVideo::query()->updateOrCreate(
        [
            'exercise_id' => $exercise->id,
            'sort_order' => 1,
        ],
        [
            'title_en' => $exercise->name_en,
            'title_ar' => $exercise->name_ar,
            'video_url' => $asset,
            'media_type' => 'gif',
            'thumbnail_url' => null,
            'duration_seconds' => 2,
            'is_placeholder' => false,
            'is_active' => true,
        ],
    );
    $updated++;
}

echo "UPDATED {$updated}\n";
