<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$rows = DB::table('exercises as e')
    ->join('training_categories as c', 'c.id', '=', 'e.training_category_id')
    ->where('c.type', 'bodybuilding')
    ->orderBy('e.id')
    ->get(['e.id', 'e.slug', 'e.name_en', 'e.name_ar', 'c.slug as category', 'e.equipment', 'e.difficulty', 'e.movement_pattern', 'e.primary_muscles', 'e.secondary_muscles']);

echo $rows->toJson();
