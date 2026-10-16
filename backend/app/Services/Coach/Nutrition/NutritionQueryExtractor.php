<?php

namespace App\Services\Coach\Nutrition;

/**
 * Phase 11D — parse calorie/macro lookup requests (not meal plans).
 * Intelligence upgrade: natural Arabic/English quantity + food parsing.
 */
class NutritionQueryExtractor
{
    public function isNutritionLookup(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        // Meal plans / diets belong to 11E — do not treat as lookup.
        if (preg_match('/\b(meal plan|nutrition plan|diet plan|bulking|cutting|خطة غذائية|جدول أكل)\b/u', $lower)) {
            return false;
        }

        // Personalized targets ("how much protein should I eat?") belong to 11E/11F — not food lookup.
        if (preg_match('/\b(how much protein should|protein should i|how many calories should|calorie target|سعرات.*(أحتاج|آكل)|كم.*(بروتين|سعرة).*(أكل|أحتاج|يوم))\b/u', $lower)
            && ! preg_match('/\d+\s*(g|kg|grams?|غرام|جرام|غم|غ)\b/u', $lower)) {
            return false;
        }

        if (preg_match(
            '/(calor|kcal|protein|carb|macro|how many|how much|سعرات|سعرة|بروتين|كارب|ماكرو|كم سعر|كم سعرة|كالوري|قديش.*(سعرة|كالوري|بروتين)|فيها كم|فيهم كم)/u',
            $lower,
        )) {
            return true;
        }

        // Quantity + known food without explicit "calories" word.
        return (bool) preg_match(
            '/(\d+(?:\.\d+)?)\s*(g|kg|grams?|غرام|جرام|غم|غ)\s+.{0,40}(chicken|دجاج|rice|رز|أرز|tuna|تونة|oats|شوفان)/u',
            $lower,
        ) || (bool) preg_match('/(تفاحة|تفاح|apple|موز|banana|بيض|egg).{0,40}(سعرة|سعرات|كالوري|calor|بروتين|protein|قديش|كم)/u', $lower)
            || (bool) preg_match('/(سعرة|سعرات|كالوري|calor|بروتين|protein|قديش|كم).{0,40}(تفاحة|تفاح|apple)/u', $lower);
    }

    /**
     * @return list<array{query: string, quantity: float, unit: string, preparation: string|null, prefer_generic: bool, brand: string|null}>
     */
    public function extractFoodItems(string $message): array
    {
        $lower = mb_strtolower($message);
        $items = [];

        // Patterns like: 200g chicken + 150g rice + 2 eggs
        if (preg_match_all(
            '/(\d+(?:\.\d+)?)\s*(g|kg|grams?|ml|eggs?|egg|bananas?|banana|apples?|apple|slices?|slice|bars?|bar)\s+(?:of\s+)?([^,+\n]+)/iu',
            $message,
            $matches,
            PREG_SET_ORDER,
        )) {
            foreach ($matches as $m) {
                $unit = $this->mapUnit($m[2]);
                $foodQuery = trim($m[3]);
                $foodQuery = preg_replace('/\b(and|with|plus)\b.*$/iu', '', $foodQuery) ?? $foodQuery;
                $items[] = $this->item((float) $m[1], $unit, $this->cleanFoodQuery($foodQuery), $lower);
            }
        }

        // "calories in 100g cooked rice" / "protein in 200g chicken breast"
        if ($items === [] && preg_match(
            '/(?:calor(?:ies|ie)?|protein|carbs?|macros?|سعرات|سعرة|بروتين).*?(?:in|of|ل|في)?\s*(\d+(?:\.\d+)?)\s*(g|kg|grams?)\s+(?:of\s+)?(.+)$/iu',
            $message,
            $m,
        )) {
            $items[] = $this->item((float) $m[1], $this->mapUnit($m[2]), $this->cleanFoodQuery(trim($m[3])), $lower);
        }

        // Arabic grams: "200 غرام صدر دجاج كم سعرة..." / "150 جرام دجاج"
        if ($items === [] && preg_match(
            '/(\d+(?:\.\d+)?)\s*(?:غرام|جرام|غم|غ)\s+(?:من\s+)?(.+)$/iu',
            $message,
            $m,
        )) {
            $items[] = $this->item((float) $m[1], 'g', $this->cleanFoodQuery(trim($m[2])), $lower);
        }

        // "نصف كيلو رز"
        if ($items === [] && preg_match('/نصف\s*كيلو\s+(.+?)(?:\?|؟|$)/iu', $message, $m)) {
            $items[] = $this->item(0.5, 'kg', $this->cleanFoodQuery(trim($m[1])), $lower);
        }

        // "2 eggs" / "3 eggs"
        if ($items === [] && preg_match('/(\d+)\s*(eggs?|bananas?|apples?)\b/iu', $message, $m)) {
            $items[] = $this->item((float) $m[1], 'piece', $m[2], $lower);
        }

        // "one apple" / "two apples" / "a banana"
        if ($items === [] && preg_match(
            '/\b(one|two|three|a|an|1|2|3)\s+(eggs?|bananas?|apples?|serving(?:\s+of)?\s+[\p{L}\s\-]+)\b/iu',
            $message,
            $m,
        )) {
            $qty = match (mb_strtolower($m[1])) {
                'one', 'a', 'an', '1' => 1.0,
                'two', '2' => 2.0,
                'three', '3' => 3.0,
                default => 1.0,
            };
            $food = trim($m[2]);
            if (preg_match('/^serving(?:\s+of)?\s+(.+)$/iu', $food, $sm)) {
                $items[] = $this->item($qty, 'serving', $this->cleanFoodQuery(trim($sm[1])), $lower);
            } else {
                $items[] = $this->item($qty, 'piece', $food, $lower);
            }
        }

        // "calories in one apple" / "how many calories are in an apple"
        if ($items === [] && preg_match(
            '/(?:calor(?:ies|ie)?|protein|carbs?|macros?|سعرات|بروتين).*?(?:in|of|ل|في)\s+(?:an?\s+|one\s+|the\s+)?(apple|banana|egg|chicken(?:\s+breast)?|tuna|rice|milk|oats?)\b/iu',
            $message,
            $m,
        )) {
            $items[] = $this->item(1.0, 'piece', trim($m[1]), $lower);
        }

        // Arabic fruits/eggs with optional definite article: التفاحة / تفاحة / تفاحتين
        if ($items === [] && preg_match('/(?:ال)?(تفاحتين|تفاحتان|تفاحة|تفاح|بيضتين|بيضتان|بيضة|بيض|موزتين|موزة|موز)\b/u', $message, $m)) {
            $token = $m[1];
            $qty = 1.0;
            if (preg_match('/(تفاحتين|تفاحتان|بيضتين|بيضتان|موزتين)/u', $token)) {
                $qty = 2.0;
            }
            $map = [
                'تفاحة' => 'apple', 'تفاح' => 'apple', 'تفاحتين' => 'apple', 'تفاحتان' => 'apple',
                'بيضة' => 'egg', 'بيض' => 'egg', 'بيضتين' => 'egg', 'بيضتان' => 'egg',
                'موزة' => 'banana', 'موز' => 'banana', 'موزتين' => 'banana',
            ];
            $items[] = $this->item($qty, 'piece', $map[$token] ?? $token, $lower);
        }

        // "الواحدة" / "واحدة" near apple already handled via piece; quantity override
        if ($items !== [] && preg_match('/(الواحدة|واحدة|واحد)\b/u', $lower)) {
            $items[0]['quantity'] = 1.0;
        }

        return $items;
    }

    /**
     * Strip trailing Arabic/English question clutter from food names.
     */
    public function cleanFoodQuery(string $foodQuery): string
    {
        $clean = $foodQuery;
        $clean = preg_replace(
            '/\s*(كم|قديش|فيها|فيهم|سعرة|سعرات|حرارية|بروتين|كارب|كالوري|calories?|protein|carbs?|macros?|how many|how much|\?|؟).*$/iu',
            '',
            $clean,
        ) ?? $clean;
        $clean = preg_replace('/[?؟!,.;:]+/u', ' ', $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);

        return $this->aliasFoodQuery($clean);
    }

    private function aliasFoodQuery(string $query): string
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return $query;
        }

        $aliases = [
            'صدر دجاج' => 'chicken breast',
            'دجاج صدر' => 'chicken breast',
            'صدر الدجاج' => 'chicken breast',
            'دجاج' => 'chicken breast',
            'تفاح' => 'apple',
            'تفاحة' => 'apple',
            'موز' => 'banana',
            'موزة' => 'banana',
            'بيض' => 'egg',
            'بيضة' => 'egg',
            'أرز' => 'rice',
            'رز' => 'rice',
            'تونة' => 'tuna',
            'حليب' => 'milk',
        ];

        foreach ($aliases as $ar => $en) {
            if ($q === $ar || str_contains($q, $ar)) {
                return $en;
            }
        }

        return $query;
    }

    /**
     * @return array{query: string, quantity: float, unit: string, preparation: string|null, prefer_generic: bool, brand: string|null}
     */
    private function item(float $quantity, string $unit, string $foodQuery, string $fullLower): array
    {
        $prep = null;
        $q = mb_strtolower($foodQuery);
        if (preg_match('/\b(cooked|مطبوخ)\b/u', $q) || preg_match('/\b(cooked|مطبوخ)\b/u', $fullLower)) {
            $prep = 'cooked';
        }
        if (preg_match('/\b(raw|dry|نيء|جاف)\b/u', $q) || preg_match('/\b(raw|dry|نيء)\b/u', $fullLower)) {
            $prep = 'raw';
        }
        if (preg_match('/\b(hard[- ]?boiled|مسلوق)\b/u', $q)) {
            $prep = 'hard_boiled';
        }
        if (preg_match('/\b(roasted|مشوي)\b/u', $q) || preg_match('/\b(roasted|مشوي)\b/u', $fullLower)) {
            $prep = 'roasted';
        }

        $preferGeneric = ! preg_match('/\b(brand|demo brand)\b/u', $fullLower);
        $brand = null;
        if (preg_match('/\bdemo brand\b/iu', $foodQuery.' '.$fullLower)) {
            $brand = 'Demo Brand';
            $preferGeneric = false;
        }

        $clean = preg_replace('/\b(cooked|raw|dry|hard[- ]?boiled|roasted|calories?|protein|carbs?|macros?|please|مطبوخ|نيء|مشوي)\b/iu', ' ', $foodQuery) ?? $foodQuery;
        $clean = $this->cleanFoodQuery($clean);

        return [
            'query' => $clean !== '' ? $clean : trim($foodQuery),
            'quantity' => $quantity,
            'unit' => $unit,
            'preparation' => $prep,
            'prefer_generic' => $preferGeneric,
            'brand' => $brand,
        ];
    }

    private function mapUnit(string $raw): string
    {
        $u = strtolower($raw);

        return match (true) {
            str_starts_with($u, 'kg') => 'kg',
            str_starts_with($u, 'g') => 'g',
            str_contains($u, 'egg') => 'piece',
            str_contains($u, 'banana') => 'piece',
            str_contains($u, 'apple') => 'piece',
            str_contains($u, 'slice') => 'piece',
            str_contains($u, 'bar') => 'piece',
            default => $u,
        };
    }
}
