<?php

namespace App\Services\Coach\Nutrition;

use App\Models\Food;

/**
 * Phase 11E — allergy HARD exclusions + dislike/exclusion soft filters.
 *
 * Limitation: allergen detection uses food.allergen_tags + name/description keywords.
 * Cross-contamination is NOT claimed. Detection is incomplete without full ingredient data.
 */
class FoodConstraintService
{
    /** @var array<string, list<string>> */
    private const ALLERGEN_ALIASES = [
        'peanut' => ['peanut', 'peanuts'],
        'egg' => ['egg', 'eggs'],
        'dairy' => ['dairy', 'milk', 'lactose', 'whole milk', 'skim'],
        'wheat' => ['wheat', 'gluten', 'bread'],
        'soy' => ['soy', 'soya'],
        'fish' => ['fish'],
        'shellfish' => ['shellfish', 'shrimp', 'crab'],
        'tree_nut' => ['tree_nut', 'almond', 'walnut', 'cashew'],
    ];

    /**
     * @param  list<string>  $allergies
     * @param  list<string>  $dislikes
     * @param  list<string>  $excluded
     * @param  list<string>  $intolerances
     */
    public function __construct(
        private readonly array $allergies = [],
        private readonly array $dislikes = [],
        private readonly array $excluded = [],
        private readonly array $intolerances = [],
        private readonly ?string $dietaryPattern = null,
    ) {}

    /**
     * @param  array<string, mixed>  $buckets
     */
    public static function fromBuckets(array $buckets): self
    {
        return new self(
            allergies: self::normList($buckets['allergies'] ?? []),
            dislikes: self::normList($buckets['disliked'] ?? $buckets['dislikes'] ?? []),
            excluded: self::normList($buckets['excluded_foods'] ?? []),
            intolerances: self::normList($buckets['intolerances'] ?? []),
            dietaryPattern: isset($buckets['dietary_pattern']) ? mb_strtolower((string) $buckets['dietary_pattern']) : null,
        );
    }

    public function isAllergyViolation(Food $food): bool
    {
        foreach ($this->allergies as $allergy) {
            if ($this->foodMatchesConstraint($food, $allergy, hard: true)) {
                return true;
            }
        }

        // Intolerances: treated as hard exclusions for meal planning safety in 11E
        // (documented as project safety model — not clinical).
        foreach ($this->intolerances as $item) {
            if ($this->foodMatchesConstraint($food, $item, hard: true)) {
                return true;
            }
        }

        return false;
    }

    public function isSoftExcluded(Food $food): bool
    {
        foreach (array_merge($this->dislikes, $this->excluded) as $token) {
            if ($this->foodMatchesConstraint($food, $token, hard: false)) {
                return true;
            }
        }

        return false;
    }

    public function violatesDietaryPattern(Food $food): bool
    {
        $pattern = $this->dietaryPattern;
        if ($pattern === null || $pattern === '' || $pattern === 'omnivore') {
            return false;
        }

        $cat = mb_strtolower((string) $food->category);
        $name = mb_strtolower($food->name_en);

        if ($pattern === 'vegetarian' || $pattern === 'vegan') {
            if ($cat === 'protein' && (str_contains($name, 'chicken') || str_contains($name, 'beef') || str_contains($name, 'fish'))) {
                return true;
            }
        }
        if ($pattern === 'vegan') {
            if ($cat === 'dairy' || str_contains($name, 'egg') || str_contains($name, 'milk')) {
                return true;
            }
        }

        $supported = config('nutrition_plan.supported_dietary_patterns', []);
        if ($pattern === 'halal' && ! in_array('halal', $supported, true)) {
            // Unsupported — caller should surface limitation; do not silently filter.
            return false;
        }

        return false;
    }

    public function isAllowed(Food $food): bool
    {
        if ($this->isAllergyViolation($food)) {
            return false;
        }
        if ($this->violatesDietaryPattern($food)) {
            return false;
        }
        if ($this->isSoftExcluded($food)) {
            return false;
        }

        return true;
    }

    /**
     * Prefer hard-allowed foods; soft exclusions filtered unless no alternative.
     *
     * @param  iterable<Food>  $foods
     * @return list<Food>
     */
    public function filterAllowed(iterable $foods, bool $allowSoftIfEmpty = false): array
    {
        $hardOk = [];
        $softBlocked = [];
        foreach ($foods as $food) {
            if ($this->isAllergyViolation($food) || $this->violatesDietaryPattern($food)) {
                continue;
            }
            if ($this->isSoftExcluded($food)) {
                $softBlocked[] = $food;

                continue;
            }
            $hardOk[] = $food;
        }

        if ($hardOk !== []) {
            return $hardOk;
        }

        return $allowSoftIfEmpty ? $softBlocked : [];
    }

    /**
     * @return list<string>
     */
    public function limitations(): array
    {
        return [
            'Allergy matching uses allergen_tags and name/description keywords only — not a complete allergen database.',
            'Cross-contamination safety is not claimed.',
            'Intolerances are treated as hard exclusions for planning safety in Phase 11E.',
        ];
    }

    private function foodMatchesConstraint(Food $food, string $token, bool $hard): bool
    {
        $token = mb_strtolower(trim($token));
        if ($token === '') {
            return false;
        }

        $tags = $food->allergen_tags ?? [];
        if (! is_array($tags)) {
            $tags = [];
        }
        $tagsLower = array_map(static fn ($t) => mb_strtolower((string) $t), $tags);

        $aliases = self::ALLERGEN_ALIASES[$token] ?? [$token];
        foreach ($aliases as $alias) {
            if (in_array($alias, $tagsLower, true) || in_array($token, $tagsLower, true)) {
                return true;
            }
        }

        $hay = mb_strtolower(
            ($food->name_en ?? '').' '.($food->description_en ?? '').' '.($food->brand ?? '').' '.($food->category ?? ''),
        );

        foreach ($aliases as $alias) {
            if ($alias !== '' && str_contains($hay, $alias)) {
                return true;
            }
        }

        // Soft dislike of "rice" / "chicken" etc. via name contains.
        if (! $hard && str_contains($hay, $token)) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<string>
     */
    private static function normList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_string($item)) {
                continue;
            }
            $t = mb_strtolower(trim($item));
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return array_values(array_unique($out));
    }
}
