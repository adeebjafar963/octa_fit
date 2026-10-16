<?php

namespace App\Services\Coach\Nutrition;

use App\Models\Food;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phase 11E — personalized nutrition plan engine using Phase 11D foods + calculator.
 */
class NutritionPlanEngine
{
    /** @var list<array{name: string, slots: list<array{role: string, query: string, preparation: ?string, base_grams: float}>}> */
    private const MEAL_TEMPLATES = [
        [
            'name' => 'Breakfast',
            'slots' => [
                ['role' => 'carb', 'query' => 'oats cooked', 'preparation' => 'cooked', 'base_grams' => 200],
                ['role' => 'protein', 'query' => 'egg hard-boiled', 'preparation' => 'hard_boiled', 'base_grams' => 100],
                ['role' => 'fruit', 'query' => 'banana', 'preparation' => 'raw', 'base_grams' => 118],
            ],
        ],
        [
            'name' => 'Lunch',
            'slots' => [
                ['role' => 'protein', 'query' => 'chicken breast cooked roasted', 'preparation' => 'roasted', 'base_grams' => 150],
                ['role' => 'carb', 'query' => 'rice cooked', 'preparation' => 'cooked', 'base_grams' => 180],
                ['role' => 'fruit', 'query' => 'apple', 'preparation' => 'raw', 'base_grams' => 182],
            ],
        ],
        [
            'name' => 'Snack',
            'slots' => [
                ['role' => 'carb', 'query' => 'bread whole-wheat', 'preparation' => 'baked', 'base_grams' => 56],
                ['role' => 'dairy', 'query' => 'milk skim', 'preparation' => null, 'base_grams' => 250],
            ],
        ],
        [
            'name' => 'Dinner',
            'slots' => [
                ['role' => 'protein', 'query' => 'chicken breast cooked roasted', 'preparation' => 'roasted', 'base_grams' => 180],
                ['role' => 'carb', 'query' => 'rice cooked', 'preparation' => 'cooked', 'base_grams' => 200],
                ['role' => 'fat', 'query' => 'olive oil', 'preparation' => null, 'base_grams' => 10],
            ],
        ],
        [
            'name' => 'Evening snack',
            'slots' => [
                ['role' => 'fruit', 'query' => 'banana', 'preparation' => 'raw', 'base_grams' => 118],
                ['role' => 'dairy', 'query' => 'milk whole', 'preparation' => null, 'base_grams' => 200],
            ],
        ],
        [
            'name' => 'Extra meal',
            'slots' => [
                ['role' => 'protein', 'query' => 'egg hard-boiled', 'preparation' => 'hard_boiled', 'base_grams' => 100],
                ['role' => 'carb', 'query' => 'oats cooked', 'preparation' => 'cooked', 'base_grams' => 150],
            ],
        ],
    ];

    /** Alternate slots for variety / replacement */
    private const ROLE_ALTERNATES = [
        'protein' => [
            ['query' => 'chicken breast cooked roasted', 'preparation' => 'roasted'],
            ['query' => 'egg hard-boiled', 'preparation' => 'hard_boiled'],
        ],
        'carb' => [
            ['query' => 'rice cooked', 'preparation' => 'cooked'],
            ['query' => 'oats cooked', 'preparation' => 'cooked'],
            ['query' => 'bread whole-wheat', 'preparation' => 'baked'],
        ],
        'fruit' => [
            ['query' => 'banana', 'preparation' => 'raw'],
            ['query' => 'apple', 'preparation' => 'raw'],
        ],
        'dairy' => [
            ['query' => 'milk skim', 'preparation' => null],
            ['query' => 'milk whole', 'preparation' => null],
        ],
        'fat' => [
            ['query' => 'olive oil', 'preparation' => null],
        ],
    ];

    public function __construct(
        private readonly NutritionIntentExtractor $intentExtractor,
        private readonly CalorieTargetCalculator $calorieCalculator,
        private readonly MacroTargetCalculator $macroCalculator,
        private readonly FoodRetrievalService $foodRetrieval,
        private readonly NutritionCalculator $nutritionCalculator,
        private readonly NutritionPlanValidator $validator,
        private readonly NutritionPreferenceService $preferences,
        private readonly NutritionPlanRepository $plans,
    ) {}

    /**
     * @param  array<string, mixed>  $userContext
     * @return array{plan: array<string, mixed>|null, intent: array<string, mixed>, errors: list<string>, preference_hints: array<string, mixed>}
     */
    public function generateFromMessage(User $user, string $message, array $userContext = [], ?string $language = 'en'): array
    {
        $hints = $this->preferences->applyMessageHints($user, $message);
        $buckets = $this->preferences->bucketsForUser($user);

        // Merge preference meals into context for intent.
        if (($buckets['meals_per_day'] ?? null) !== null) {
            $userContext['nutrition'] = array_merge(
                is_array($userContext['nutrition'] ?? null) ? $userContext['nutrition'] : [],
                ['meals_per_day' => $buckets['meals_per_day']],
            );
            $userContext['meals_per_day'] = $buckets['meals_per_day'];
        }

        $intent = $this->intentExtractor->extract($message, $userContext);

        $previous = $this->plans->latestActive($user);
        $shouldRegen = ($intent['is_regeneration'] ?? false)
            || ($intent['is_replacement'] ?? false)
            || (($hints['changed'] ?? false) && $previous !== null && ($intent['is_preference_update'] ?? false));

        if (! ($intent['is_nutrition_plan_request'] ?? false) && ! $shouldRegen) {
            return ['plan' => null, 'intent' => $intent, 'errors' => [], 'preference_hints' => $hints];
        }

        // Preference-only with no prior plan → store prefs, no plan yet.
        if (($intent['is_preference_update'] ?? false) && ! ($intent['is_nutrition_plan_request'] ?? false) && $previous === null && ! $shouldRegen) {
            return ['plan' => null, 'intent' => $intent, 'errors' => [], 'preference_hints' => $hints];
        }

        if ($shouldRegen && $previous !== null) {
            $base = $previous->payload;
            $intent['goal'] = $intent['goal'] ?? ($base['goal'] ?? 'maintenance');
            if (($intent['explicit_calories'] ?? null) === null) {
                $intent['explicit_calories'] = $base['calorie_target'] ?? null;
            }
            if (($intent['meals_per_day'] ?? null) === null) {
                $intent['meals_per_day'] = $base['meals_per_day'] ?? null;
            }
            if (($intent['duration_days'] ?? null) === null) {
                $intent['duration_days'] = $base['duration_days'] ?? null;
            }
            $intent['is_nutrition_plan_request'] = true;
        }

        return $this->generate($user, $intent, $userContext, $buckets, $language, $previous);
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $userContext
     * @param  array<string, mixed>  $buckets
     * @return array{plan: array<string, mixed>|null, intent: array<string, mixed>, errors: list<string>, preference_hints: array<string, mixed>}
     */
    public function generate(
        User $user,
        array $intent,
        array $userContext,
        array $buckets,
        ?string $language = 'en',
        ?\App\Models\NutritionPlan $previous = null,
    ): array {
        $goal = (string) ($intent['goal'] ?? 'maintenance');
        $explicit = isset($intent['explicit_calories']) ? (int) $intent['explicit_calories'] : null;

        $calorie = $this->calorieCalculator->calculate($userContext, $goal, $explicit);
        if (! ($calorie['ok'] ?? false) || ($calorie['calorie_target'] ?? null) === null) {
            return [
                'plan' => null,
                'intent' => $intent,
                'errors' => $calorie['errors'] ?? ['calorie_calculation_failed'],
                'preference_hints' => [],
            ];
        }

        $macros = $this->macroCalculator->calculate((int) $calorie['calorie_target'], $goal, $userContext);
        if (! ($macros['ok'] ?? false)) {
            return [
                'plan' => null,
                'intent' => $intent,
                'errors' => $macros['errors'] ?? ['macro_calculation_failed'],
                'preference_hints' => [],
            ];
        }

        $mealsPerDay = (int) ($intent['meals_per_day']
            ?? $buckets['meals_per_day']
            ?? $userContext['nutrition']['meals_per_day']
            ?? $userContext['meals_per_day']
            ?? config('nutrition_plan.default_meals_per_day', 4));
        $mealsPerDay = max(
            (int) config('nutrition_plan.meals_per_day_min', 2),
            min((int) config('nutrition_plan.meals_per_day_max', 6), $mealsPerDay),
        );

        $duration = (int) ($intent['duration_days'] ?? config('nutrition_plan.default_duration_days', 3));
        $duration = max(1, min((int) config('nutrition_plan.max_duration_days', 7), $duration));

        $pattern = $buckets['dietary_pattern'] ?? null;
        $supported = config('nutrition_plan.supported_dietary_patterns', []);
        $limitations = [];
        if ($pattern !== null && $pattern !== '' && ! in_array($pattern, $supported, true)) {
            $limitations[] = 'dietary_pattern_unsupported:'.$pattern;
            // Continue with omnivore filtering rather than inventing foods.
            $pattern = null;
        }

        $constraints = FoodConstraintService::fromBuckets(array_merge($buckets, [
            'dietary_pattern' => $pattern,
        ]));
        $limitations = array_merge($limitations, $constraints->limitations());

        if (($buckets['cost_preference'] ?? null) === 'budget') {
            $limitations[] = 'Cost optimization not available — budget preference noted only; no price data.';
        }

        $templates = array_slice(self::MEAL_TEMPLATES, 0, $mealsPerDay);
        $days = [];
        $poolCache = [];

        for ($d = 1; $d <= $duration; $d++) {
            $meals = [];
            $dayFoodRows = [];

            foreach ($templates as $mealIndex => $template) {
                $slotOffset = ($d - 1) % 2; // light variety across days
                $entries = [];

                foreach ($template['slots'] as $slot) {
                    $food = $this->resolveSlotFood($slot, $constraints, $poolCache, $slotOffset);
                    if ($food === null) {
                        $limitations[] = 'slot_unfilled:'.$template['name'].':'.$slot['role'];

                        continue;
                    }
                    $entries[] = [
                        'food' => $food,
                        'base_grams' => (float) $slot['base_grams'],
                        'role' => $slot['role'],
                    ];
                }

                if ($entries === []) {
                    return [
                        'plan' => null,
                        'intent' => $intent,
                        'errors' => ['no_compatible_foods_for_meal:'.$template['name']],
                        'preference_hints' => [],
                    ];
                }

                $meals[] = [
                    'name' => $template['name'],
                    'entries' => $entries,
                ];
            }

            // Scale whole day toward calorie target.
            $scaled = $this->scaleDayMeals($meals, (int) $calorie['calorie_target']);
            $builtMeals = [];
            foreach ($scaled as $mi => $mealData) {
                $foodRows = [];
                foreach ($mealData['items'] as $item) {
                    try {
                        $row = $this->nutritionCalculator->calculateForFood($item['food'], $item['grams'], 'g');
                    } catch (\InvalidArgumentException $e) {
                        return [
                            'plan' => null,
                            'intent' => $intent,
                            'errors' => ['calculation_failed:'.$e->getMessage()],
                            'preference_hints' => [],
                        ];
                    }
                    $foodRows[] = $this->toPlanFood($row);
                    $dayFoodRows[] = $row;
                }
                $builtMeals[] = [
                    'meal_number' => $mi + 1,
                    'name' => $mealData['name'],
                    'foods' => $foodRows,
                    'meal_totals' => $this->nutritionCalculator->sumTotals($foodRows),
                ];
            }

            $days[] = [
                'day_number' => $d,
                'meals' => $builtMeals,
                'daily_totals' => $this->nutritionCalculator->sumTotals($dayFoodRows),
            ];
        }

        $plan = [
            'schema_version' => '11e.1',
            'plan_id' => null,
            'version' => null,
            'status' => 'active',
            'goal' => $goal,
            'duration_days' => $duration,
            'meals_per_day' => $mealsPerDay,
            'calorie_target' => (int) $calorie['calorie_target'],
            'macro_targets' => [
                'protein_g' => $macros['protein_g'],
                'carbohydrates_g' => $macros['carbohydrates_g'],
                'fat_g' => $macros['fat_g'],
            ],
            'energy' => [
                'bmr_kcal' => $calorie['bmr_kcal'],
                'tdee_kcal' => $calorie['tdee_kcal'],
                'activity_category' => $calorie['activity_category'],
                'activity_multiplier' => $calorie['activity_multiplier'],
                'goal_adjustment' => $calorie['goal_adjustment'],
            ],
            'days' => $days,
            'assumptions' => array_values(array_unique(array_merge(
                $calorie['assumptions'] ?? [],
                $macros['assumptions'] ?? [],
            ))),
            'preferences_applied' => [
                'allergies' => $buckets['allergies'] ?? [],
                'disliked' => $buckets['disliked'] ?? [],
                'excluded_foods' => $buckets['excluded_foods'] ?? [],
                'intolerances' => $buckets['intolerances'] ?? [],
                'dietary_pattern' => $pattern,
                'meals_per_day' => $mealsPerDay,
            ],
            'exclusions_applied' => array_values(array_unique(array_merge(
                $buckets['allergies'] ?? [],
                $buckets['excluded_foods'] ?? [],
            ))),
            'limitations' => array_values(array_unique($limitations)),
            'calorie_tolerance_kcal' => (int) config('nutrition_plan.calorie_tolerance_kcal', 120),
            'macro_tolerance_g' => (int) config('nutrition_plan.macro_tolerance_g', 25),
            'grounded' => true,
            'created_at' => now()->toIso8601String(),
        ];

        $validated = $this->validator->validate($plan, $constraints);
        if (! $validated['ok']) {
            // Retry once with looser calorie tolerance by adjusting scale further.
            Log::debug('coach.nutrition_plan.validation_failed', ['errors' => $validated['errors']]);

            // Soften: if only calorie_tolerance_exceeded, try rescale day 1 factor.
            if ($this->onlyToleranceErrors($validated['errors'])) {
                $plan = $this->forceRescalePlan($plan, (int) $calorie['calorie_target'], $constraints);
                $validated = $this->validator->validate($plan, $constraints);
            }
        }

        if (! $validated['ok']) {
            return [
                'plan' => null,
                'intent' => $intent,
                'errors' => $validated['errors'],
                'preference_hints' => [],
            ];
        }

        $plan = $validated['plan'];
        $stored = $this->plans->saveNewVersion($user, $plan, $previous);
        $plan = $stored->payload;
        $plan['plan_id'] = $stored->plan_uuid;
        $plan['version'] = $stored->version;
        $plan['db_id'] = $stored->id;

        return [
            'plan' => $plan,
            'intent' => $intent,
            'errors' => [],
            'preference_hints' => [],
        ];
    }

    /**
     * @param  array{role: string, query: string, preparation: ?string, base_grams: float}  $slot
     * @param  array<string, Food|null>  $poolCache
     */
    private function resolveSlotFood(array $slot, FoodConstraintService $constraints, array &$poolCache, int $altOffset): ?Food
    {
        $candidates = [];
        $role = $slot['role'];
        $alts = self::ROLE_ALTERNATES[$role] ?? [['query' => $slot['query'], 'preparation' => $slot['preparation']]];
        // Rotate alternate starting point for variety.
        $rotated = array_merge(
            array_slice($alts, $altOffset % max(1, count($alts))),
            array_slice($alts, 0, $altOffset % max(1, count($alts))),
        );
        // Prefer exact slot query first.
        array_unshift($rotated, ['query' => $slot['query'], 'preparation' => $slot['preparation']]);

        foreach ($rotated as $alt) {
            $key = ($alt['query'] ?? '').'|'.($alt['preparation'] ?? '');
            if (! array_key_exists($key, $poolCache)) {
                $match = $this->foodRetrieval->search((string) $alt['query'], [
                    'preparation' => $alt['preparation'] ?? null,
                    'prefer_generic' => true,
                ]);
                $food = null;
                if (in_array($match['status'], ['exact_match', 'strong_match'], true) && ($match['foods'][0]['food_id'] ?? null)) {
                    $food = $this->foodRetrieval->findById((int) $match['foods'][0]['food_id']);
                }
                $poolCache[$key] = $food;
            }
            $food = $poolCache[$key];
            if ($food instanceof Food) {
                $candidates[] = $food;
            }
        }

        $allowed = $constraints->filterAllowed($candidates, allowSoftIfEmpty: false);
        if ($allowed === []) {
            // Fallback cross-role search within catalog (still constrained).
            $fallbacks = match ($role) {
                'dairy' => [
                    ['query' => 'banana', 'preparation' => 'raw'],
                    ['query' => 'apple', 'preparation' => 'raw'],
                    ['query' => 'oats cooked', 'preparation' => 'cooked'],
                ],
                'protein' => [
                    ['query' => 'chicken breast cooked roasted', 'preparation' => 'roasted'],
                    ['query' => 'egg hard-boiled', 'preparation' => 'hard_boiled'],
                ],
                default => [],
            };
            foreach ($fallbacks as $alt) {
                $match = $this->foodRetrieval->search((string) $alt['query'], [
                    'preparation' => $alt['preparation'] ?? null,
                    'prefer_generic' => true,
                ]);
                if (in_array($match['status'], ['exact_match', 'strong_match'], true) && ($match['foods'][0]['food_id'] ?? null)) {
                    $food = $this->foodRetrieval->findById((int) $match['foods'][0]['food_id']);
                    if ($food instanceof Food && $constraints->isAllowed($food)) {
                        return $food;
                    }
                }
            }

            return null;
        }

        return $allowed[0];
    }

    /**
     * @param  list<array{name: string, entries: list<array{food: Food, base_grams: float, role: string}>}>  $meals
     * @return list<array{name: string, items: list<array{food: Food, grams: float}>}>
     */
    private function scaleDayMeals(array $meals, int $calorieTarget): array
    {
        $baseItems = [];
        foreach ($meals as $meal) {
            foreach ($meal['entries'] as $entry) {
                $baseItems[] = ['food' => $entry['food'], 'grams' => $entry['base_grams'], 'meal' => $meal['name']];
            }
        }

        $baseKcal = 0.0;
        foreach ($baseItems as $item) {
            /** @var Food $f */
            $f = $item['food'];
            $baseKcal += ((float) $f->calories_kcal_per_100g) * ($item['grams'] / 100.0);
        }

        $scale = $baseKcal > 0 ? ($calorieTarget / $baseKcal) : 1.0;
        $minG = (float) config('nutrition_plan.min_food_grams', 5);
        $maxG = (float) config('nutrition_plan.max_food_grams', 800);

        $out = [];
        foreach ($meals as $meal) {
            $items = [];
            foreach ($meal['entries'] as $entry) {
                $grams = round($entry['base_grams'] * $scale, 1);
                $grams = max($minG, min($maxG, $grams));
                // Prefer scaling carb/protein roles slightly more than tiny fat portions.
                $items[] = ['food' => $entry['food'], 'grams' => $grams];
            }
            $out[] = ['name' => $meal['name'], 'items' => $items];
        }

        // Fine-tune by adjusting largest carb item if still outside tolerance.
        $tol = (float) config('nutrition_plan.calorie_tolerance_kcal', 120);
        for ($i = 0; $i < 5; $i++) {
            $actual = $this->estimateDayKcal($out);
            $diff = $calorieTarget - $actual;
            if (abs($diff) <= $tol) {
                break;
            }
            $out = $this->nudgeLargestCarb($out, $diff);
        }

        return $out;
    }

    /**
     * @param  list<array{name: string, items: list<array{food: Food, grams: float}>}>  $meals
     */
    private function estimateDayKcal(array $meals): float
    {
        $kcal = 0.0;
        foreach ($meals as $meal) {
            foreach ($meal['items'] as $item) {
                $kcal += ((float) $item['food']->calories_kcal_per_100g) * ($item['grams'] / 100.0);
            }
        }

        return $kcal;
    }

    /**
     * @param  list<array{name: string, items: list<array{food: Food, grams: float}>}>  $meals
     * @return list<array{name: string, items: list<array{food: Food, grams: float}>}>
     */
    private function nudgeLargestCarb(array $meals, float $diffKcal): array
    {
        $maxG = (float) config('nutrition_plan.max_food_grams', 800);
        $minG = (float) config('nutrition_plan.min_food_grams', 5);
        $best = null;
        $bestCal = 0.0;
        foreach ($meals as $mi => $meal) {
            foreach ($meal['items'] as $ii => $item) {
                $c = (float) $item['food']->calories_kcal_per_100g;
                $cat = (string) $item['food']->category;
                if (($cat === 'grain' || $cat === 'fruit') && $c > $bestCal) {
                    $bestCal = $c;
                    $best = [$mi, $ii];
                }
            }
        }
        if ($best === null || $bestCal <= 0) {
            return $meals;
        }
        [$mi, $ii] = $best;
        $deltaGrams = ($diffKcal / $bestCal) * 100.0;
        $meals[$mi]['items'][$ii]['grams'] = max($minG, min($maxG, round($meals[$mi]['items'][$ii]['grams'] + $deltaGrams, 1)));

        return $meals;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toPlanFood(array $row): array
    {
        return [
            'food_id' => $row['food_id'],
            'source' => $row['source'],
            'external_id' => $row['external_id'],
            'name' => $row['name'],
            'name_en' => $row['name_en'] ?? $row['name'],
            'name_ar' => $row['name_ar'] ?? null,
            'brand' => $row['brand'],
            'preparation' => $row['preparation'],
            'quantity' => $row['quantity'],
            'unit' => $row['unit'],
            'normalized_grams' => $row['normalized_grams'],
            'calories_kcal' => $row['calories_kcal'],
            'protein_g' => $row['protein_g'],
            'carbohydrates_g' => $row['carbohydrates_g'],
            'fat_g' => $row['fat_g'],
            'fiber_g' => $row['fiber_g'] ?? null,
            'sugar_g' => $row['sugar_g'] ?? null,
            'sodium_mg' => $row['sodium_mg'] ?? null,
            'grounded' => true,
            'match_status' => $row['match_status'] ?? 'exact_match',
        ];
    }

    /**
     * @param  list<string>  $errors
     */
    private function onlyToleranceErrors(array $errors): bool
    {
        foreach ($errors as $e) {
            if (! str_contains($e, 'tolerance') && ! str_contains($e, 'protein_tolerance')) {
                return false;
            }
        }

        return $errors !== [];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function forceRescalePlan(array $plan, int $calorieTarget, FoodConstraintService $constraints): array
    {
        // Rebuild day 1 scale only — keep structure.
        foreach ($plan['days'] as $di => $day) {
            $allRows = [];
            foreach ($day['meals'] as $mi => $meal) {
                $current = (float) ($meal['meal_totals']['calories_kcal'] ?? 0);
                // redistribute later
                $allRows = array_merge($allRows, $meal['foods']);
            }
            $dayKcal = (float) ($day['daily_totals']['calories_kcal'] ?? 0);
            if ($dayKcal <= 0) {
                continue;
            }
            $factor = $calorieTarget / $dayKcal;
            $newMeals = [];
            $dayFoods = [];
            foreach ($day['meals'] as $meal) {
                $foods = [];
                foreach ($meal['foods'] as $f) {
                    $grams = max(
                        (float) config('nutrition_plan.min_food_grams', 5),
                        min(
                            (float) config('nutrition_plan.max_food_grams', 800),
                            round(((float) $f['normalized_grams']) * $factor, 1),
                        ),
                    );
                    $model = $this->foodRetrieval->findById((int) $f['food_id']);
                    if ($model === null) {
                        continue;
                    }
                    $row = $this->nutritionCalculator->calculateForFood($model, $grams, 'g');
                    $foods[] = $this->toPlanFood($row);
                    $dayFoods[] = $row;
                }
                $newMeals[] = [
                    'meal_number' => $meal['meal_number'],
                    'name' => $meal['name'],
                    'foods' => $foods,
                    'meal_totals' => $this->nutritionCalculator->sumTotals($foods),
                ];
            }
            $plan['days'][$di]['meals'] = $newMeals;
            $plan['days'][$di]['daily_totals'] = $this->nutritionCalculator->sumTotals($dayFoods);
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public function formatMessage(array $plan, string $language = 'en'): string
    {
        $lines = [];
        $lines[] = $language === 'ar' ? 'خطة تغذية' : 'Nutrition plan';
        $lines[] = '';
        $lines[] = ($language === 'ar' ? 'الهدف: ' : 'Goal: ').($plan['goal'] ?? '');
        $lines[] = ($language === 'ar' ? 'السعرات المستهدفة: ' : 'Calorie target: ').($plan['calorie_target'] ?? '').' kcal';
        $macros = $plan['macro_targets'] ?? [];
        $lines[] = 'Macros: P '.($macros['protein_g'] ?? '?').'g / C '.($macros['carbohydrates_g'] ?? '?').'g / F '.($macros['fat_g'] ?? '?').'g';
        $lines[] = 'Meals/day: '.($plan['meals_per_day'] ?? '').' · Days: '.($plan['duration_days'] ?? '');
        $lines[] = 'Version: v'.($plan['version'] ?? '1');
        $lines[] = '';

        foreach ($plan['days'] ?? [] as $day) {
            $lines[] = 'Day '.($day['day_number'] ?? '?');
            foreach ($day['meals'] ?? [] as $meal) {
                $lines[] = '  '.($meal['name'] ?? 'Meal').' (~'.($meal['meal_totals']['calories_kcal'] ?? '?').' kcal)';
                foreach ($meal['foods'] ?? [] as $food) {
                    $lines[] = '    • '.($food['name'] ?? 'Food').' — '
                        .($food['normalized_grams'] ?? $food['quantity'] ?? '?').'g'
                        .' (#'.$food['food_id'].')'
                        .' · '.($food['calories_kcal'] ?? '?').' kcal';
                }
            }
            $tot = $day['daily_totals'] ?? [];
            $lines[] = '  Daily total: '.($tot['calories_kcal'] ?? '?').' kcal'
                .' · P '.($tot['protein_g'] ?? '?').'g'
                .' · C '.($tot['carbohydrates_g'] ?? '?').'g'
                .' · F '.($tot['fat_g'] ?? '?').'g';
            $lines[] = '';
        }

        if (! empty($plan['exclusions_applied'])) {
            $lines[] = 'Exclusions applied: '.implode(', ', $plan['exclusions_applied']);
        }
        $lines[] = $language === 'ar'
            ? 'ملاحظة: تقديرات غذائية غير طبية — القيم من قاعدة الأغذية فقط.'
            : 'Note: Non-medical estimates. All nutrition values come from the grounded food database.';

        return implode("\n", $lines);
    }
}
