<?php

namespace Tests\Unit;

use App\Support\BodybuildingExerciseMedia;
use PHPUnit\Framework\TestCase;

class BodybuildingExerciseMediaTest extends TestCase
{
    public function test_bench_press_maps_to_the_chest_gif(): void
    {
        $this->assertSame(
            'assets/exercises/bodybuilding/chest/bb_chest_barbell_bench_press.gif',
            BodybuildingExerciseMedia::assetForSlug('bb-chest-barbell-bench-press'),
        );
    }

    public function test_full_body_slug_uses_the_full_body_folder(): void
    {
        $this->assertSame(
            'assets/exercises/bodybuilding/full_body/bb_full_body_burpee.gif',
            BodybuildingExerciseMedia::assetForSlug('bb-full-body-burpee'),
        );
    }

    public function test_non_bodybuilding_slugs_are_ignored(): void
    {
        $this->assertNull(BodybuildingExerciseMedia::assetForSlug('mma-jab'));
    }
}
