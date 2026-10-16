<?php

namespace App\Services\Coach\Nutrition;

/**
 * Phase 11E — nutrition intent (plan vs lookup vs preference update).
 */
class NutritionIntentExtractor
{
    /**
     * @param  array<string, mixed>  $userContext
     * @return array<string, mixed>
     */
    public function extract(string $message, array $userContext = []): array
    {
        $lower = mb_strtolower(trim($message));
        $intent = [
            'is_nutrition_plan_request' => false,
            'is_preference_update' => false,
            'is_regeneration' => false,
            'is_replacement' => false,
            'type' => null,
            'goal' => $this->goalFromContext($userContext),
            'explicit_calories' => null,
            'meals_per_day' => null,
            'duration_days' => null,
            'replace_food' => null,
            'exclude_tokens' => [],
            'from_profile' => false,
        ];

        if ($lower === '') {
            return $intent;
        }

        // Preference-only statements (may still trigger regen if active plan exists).
        $prefOnly = (bool) preg_match(
            '/\b(allergic to|allergy to|don\'t like|do not like|dislike|don\'t eat|do not eat|i hate)\b/iu',
            $lower,
        ) || (bool) preg_match(
            '/(ما\s*بحب|ما\s*بدي|ما\s*فيني\s*اكل|ماني\s*قادر\s*اكل|لا\s*تحطلي|شيل)/u',
            $lower,
        );
        $prefOnly = $prefOnly && ! preg_match(
            '/\b(meal plan|diet plan|nutrition plan|bulking|cutting|calories?\s+meal|create|give me|make me|برنامج\s*تدريب)\b/iu',
            $lower,
        );

        if ($prefOnly) {
            $intent['is_preference_update'] = true;
            $intent['is_regeneration'] = true;
            $intent['type'] = 'preference_update';
        }

        if (preg_match('/\b(replace|remove|without|no more)\s+([a-z][a-z\s\-]{1,30})/iu', $message, $m)) {
            $intent['is_replacement'] = true;
            $intent['is_regeneration'] = true;
            $intent['replace_food'] = mb_strtolower(trim($m[2]));
            $intent['exclude_tokens'][] = mb_strtolower(trim(preg_replace('/\b(please|from (the )?plan).*$/iu', '', $m[2]) ?? $m[2]));
            $intent['type'] = $intent['type'] ?? 'food_replacement';
        } elseif (preg_match('/(بدل|شير|شيل|غيرلي|بدون)\s*([\p{L}\s]{2,30})/u', $lower, $m)) {
            $intent['is_replacement'] = true;
            $intent['is_regeneration'] = true;
            $intent['replace_food'] = trim($m[2]);
            $intent['exclude_tokens'][] = trim($m[2]);
            $intent['type'] = $intent['type'] ?? 'food_replacement';
        }

        if (preg_match('/\b(regenerate|revise|update (the |my )?plan|change (the |my )?plan)\b/iu', $lower)
            || preg_match('/(زود\s*(ال)?بروتين|خفف\s*(ال)?سعرات|خليها\s*\d\s*وجبات?)/u', $lower)) {
            $intent['is_regeneration'] = true;
            $intent['type'] = $intent['type'] ?? 'nutrition_plan';
        }

        $planCue = (bool) preg_match(
            '/\b(meal plan|nutrition plan|diet plan|diet|meal prep|bulking diet|cutting diet|cutting meal|bulking meal|high[- ]protein (plan|diet)|خطة غذائية|خطة\s*غذائية|نظام غذائي|نظام\s*غذائي|جدول أكل|خطة أكل)\b/u',
            $lower,
        ) || (bool) preg_match(
            '/\b(give me|create|make me|build|generate|want|need|بدي|اعمللي)\b.*\b(diet|meal plan|nutrition|bulking|cutting|macros|نظام\s*غذا|خطة\s*غذا)\b/u',
            $lower,
        ) || (bool) preg_match(
            '/\b(\d{3,4})\s*(kcal|calorie|calories)\b.*\b(plan|diet|meal)\b/u',
            $lower,
        ) || (bool) preg_match(
            '/\b(plan|diet|meals)\b.*\b(\d{3,4})\s*(kcal|calorie|calories)\b/u',
            $lower,
        );

        if ($planCue || $intent['is_regeneration'] || $intent['is_replacement']) {
            $intent['is_nutrition_plan_request'] = true;
            $intent['type'] = $intent['type'] ?? 'nutrition_plan';
        }

        // Goals — only as nutrition plan when diet/meal vocabulary present OR explicit calorie target later.
        // Bare "تضخيم" inside a gym program request must NOT force a nutrition plan.
        $dietVocab = (bool) preg_match(
            '/(meal|diet|nutrition|غذا|اكل|أكل|سعرات|calorie|macros?|نظام\s*غذا|خطة\s*غذا)/u',
            $lower,
        );
        if ($dietVocab && preg_match('/\b(bulk|bulking|muscle gain|surplus|زيادة وزن|تضخيم)\b/u', $lower)) {
            $intent['goal'] = 'muscle_gain';
            $intent['type'] = 'bulking';
            $intent['is_nutrition_plan_request'] = true;
        } elseif ($dietVocab && preg_match('/\b(cut|cutting|fat loss|deficit|deficit diet|تنشيف|خسارة دهون)\b/u', $lower)) {
            $intent['goal'] = 'fat_loss';
            $intent['type'] = 'cutting';
            $intent['is_nutrition_plan_request'] = true;
        } elseif ($dietVocab && preg_match('/\b(maintain|maintenance|محافظة)\b/u', $lower)) {
            $intent['goal'] = 'maintenance';
            $intent['type'] = 'maintenance';
            $intent['is_nutrition_plan_request'] = true;
        } elseif (preg_match('/\b(high[- ]protein|high protein)\b/u', $lower)) {
            $intent['goal'] = 'high_protein';
            $intent['type'] = 'high_protein';
            $intent['is_nutrition_plan_request'] = true;
        }

        if (preg_match('/\b(\d{3,4})\s*(kcal|calorie|calories|سعرة|سعرات)\b/u', $lower, $m)) {
            $intent['explicit_calories'] = (int) $m[1];
            $intent['is_nutrition_plan_request'] = true;
            $intent['type'] = $intent['type'] ?? 'calorie_target';
        }

        if (preg_match('/\b(\d)\s*meals?\b/u', $lower, $m)
            || preg_match('/(\d)\s*وجبات?/u', $lower, $m)) {
            $intent['meals_per_day'] = (int) $m[1];
            $intent['is_nutrition_plan_request'] = true;
            $intent['type'] = $intent['type'] ?? 'nutrition_plan';
        }

        if (preg_match('/\b(\d)\s*[- ]?day\b/u', $lower, $m)) {
            $intent['duration_days'] = (int) $m[1];
        } elseif (preg_match('/\b(week|7[- ]day|weekly)\b/u', $lower)) {
            $intent['duration_days'] = 7;
        }

        // Profile meals default marker
        $profileMeals = $userContext['nutrition']['meals_per_day']
            ?? $userContext['meals_per_day']
            ?? null;
        if ($intent['meals_per_day'] === null && is_numeric($profileMeals)) {
            $intent['meals_per_day'] = (int) $profileMeals;
            $intent['from_profile'] = true;
        }

        return $intent;
    }

    /**
     * @param  array<string, mixed>  $userContext
     */
    private function goalFromContext(array $userContext): string
    {
        $g = mb_strtolower((string) (
            $userContext['goals']['primary_goal']
            ?? $userContext['primary_goal']
            ?? 'maintenance'
        ));

        return match (true) {
            str_contains($g, 'bulk') || str_contains($g, 'muscle') || $g === 'hypertrophy' => 'muscle_gain',
            str_contains($g, 'cut') || str_contains($g, 'fat') || str_contains($g, 'loss') => 'fat_loss',
            default => 'maintenance',
        };
    }
}
