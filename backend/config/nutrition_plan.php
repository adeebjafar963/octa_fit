<?php

/**
 * Phase 11E — documented deterministic nutrition-plan configuration.
 * Not clinical advice. Values are project defaults for demos/tests.
 */
return [
    'schema_version' => '11e.1',

    // Mifflin–St Jeor activity multipliers (documented categories only).
    'activity_multipliers' => [
        'sedentary' => 1.2,
        'lightly_active' => 1.375,
        'moderately_active' => 1.55,
        'very_active' => 1.725,
        'extra_active' => 1.9,
    ],
    'default_activity' => 'moderately_active',

    // Moderate goal adjustments relative to estimated TDEE (not universal optima).
    'goal_calorie_adjustments' => [
        'maintenance' => 0.0,
        'muscle_gain' => 0.10, // +10% surplus
        'fat_loss' => -0.15, // −15% deficit
        'high_protein' => 0.0,
    ],

    // Soft floors/ceilings to avoid extreme targets (not medical safety claims).
    'calorie_floor_kcal' => 1400,
    'calorie_ceiling_kcal' => 4500,

    // Protein g per kg body weight by goal (configurable factors).
    'protein_g_per_kg' => [
        'maintenance' => 1.6,
        'muscle_gain' => 1.8,
        'fat_loss' => 2.0,
        'high_protein' => 2.0,
    ],
    'default_protein_g_per_kg' => 1.6,
    'fallback_weight_kg' => 70.0,

    // Fat as fraction of target calories.
    'fat_calorie_fraction' => [
        'maintenance' => 0.28,
        'muscle_gain' => 0.25,
        'fat_loss' => 0.28,
        'high_protein' => 0.25,
    ],
    'default_fat_calorie_fraction' => 0.28,

    'kcal_per_g' => [
        'protein' => 4.0,
        'carbohydrates' => 4.0,
        'fat' => 9.0,
    ],

    // Acceptable deviation from targets after quantity scaling.
    'calorie_tolerance_kcal' => 120,
    'macro_tolerance_g' => 25,

    'meals_per_day_min' => 2,
    'meals_per_day_max' => 6,
    'default_meals_per_day' => 4,
    'default_duration_days' => 3,
    'max_duration_days' => 7,

    // Per-food quantity caps (grams) to reject absurd servings.
    'max_food_grams' => 800,
    'min_food_grams' => 5,

    // Dietary patterns currently supported with fixture catalog.
    'supported_dietary_patterns' => [
        'omnivore',
        'vegetarian',
        'high_protein',
    ],
];
