<?php

namespace App\Services\Coach\Semantic;

/**
 * Detects compound / multi-intent requests in one message.
 * Concept-level cues only — not sentence-specific answer keys.
 */
class MultiIntentDetector
{
    /**
     * @return array{
     *   is_multi: bool,
     *   intents: list<string>,
     *   segments: list<string>
     * }
     */
    public function detect(string $message, SemanticIntentContract $primary): array
    {
        $norm = mb_strtolower(trim($message));
        $hasTraining = (bool) preg_match(
            '/(برنامج|تمرين|تمارين|خطة\s*تدريب|خطه\s*تدريب|workout|program|gym|جيم|mma|حديد|صدر|اكتاف|أكتاف|ظهر|ارجل|أرجل|رجلين|كمال|bodybuilding)/u',
            $norm,
        );
        $hasNutrition = (bool) preg_match(
            '/(نظام\s*غذا|نظام\s*تنشيف|نظام\s*تضخيم|خطة\s*غذا|خطه\s*غذا|سعرة|سعرات|بروتين|اكل|أكل|غذا|calorie|protein|meal\s*plan|diet|ماكرو|وجبات)/u',
            $norm,
        );
        $compoundCue = (bool) preg_match(
            '/(و\s*بنفس\s*الوقت|وكمان|وكمان\s*بدي|وبدي\s*كمان|and\s+also|also\s+|وكذلك|مع\s*نظام|مع\s*برنامج|وقلي|وقولي|و\s*اكل|و\s*أكل|و\s*غذا|وتغذيه|وتغذية|\band\s+(a\s+)?(meal|diet|nutrition|food|eating))/u',
            $norm,
        )
            || ($hasTraining && $hasNutrition && (bool) preg_match('/(خطه|خطة|برنامج|plan|program|نظام)/u', $norm));

        $intents = [];
        if ($hasTraining && in_array($primary->intent, [
            'training_plan_create',
            'training_exercise_recommendation',
            'training_plan_modify',
        ], true)) {
            $intents[] = $primary->intent;
        } elseif ($hasTraining && $hasNutrition && $compoundCue) {
            $intents[] = preg_match('/(برنامج|plan|program|جدول)/u', $norm)
                ? 'training_plan_create'
                : 'training_exercise_recommendation';
        }

        if ($hasNutrition && in_array($primary->intent, [
            'nutrition_lookup',
            'nutrition_plan_create',
        ], true)) {
            if (! in_array($primary->intent, $intents, true)) {
                $intents[] = $primary->intent;
            }
        } elseif ($hasNutrition && $hasTraining && $compoundCue) {
            $nut = preg_match('/(نظام|خطة\s*غذا|meal|diet|سعرة)/u', $norm)
                ? 'nutrition_plan_create'
                : 'nutrition_lookup';
            if (! in_array($nut, $intents, true)) {
                $intents[] = $nut;
            }
        }

        // Dual from primary + secondary domain.
        if ($primary->intent === 'training_plan_create' && $hasNutrition && ($compoundCue || $hasTraining)) {
            $intents = array_values(array_unique(array_merge(
                ['training_plan_create'],
                [preg_match('/(نظام|خطة\s*غذا|خطه\s*غذا|meal|diet|\d{3,4}\s*سعر|تنشيف|تضخيم)/u', $norm)
                    ? 'nutrition_plan_create'
                    : 'nutrition_lookup'],
            )));
        }
        if ($primary->intent === 'nutrition_plan_create' && $hasTraining && $compoundCue) {
            // Prefer training-primary ordering for compound create requests.
            $intents = ['training_plan_create', 'nutrition_plan_create'];
        }
        // If local classified nutrition-only but message clearly pairs training days + diet system.
        if ($hasTraining && $hasNutrition && count($intents) < 2
            && preg_match('/(\d)\s*(ايام|أيام|days?)|كمال|mma|bodybuilding|جيم/u', $norm)
            && preg_match('/(نظام|diet|meal|غذا|تنشيف|تضخيم)/u', $norm)) {
            $intents = ['training_plan_create', 'nutrition_plan_create'];
        }

        $isMulti = count($intents) >= 2;

        return [
            'is_multi' => $isMulti,
            'intents' => $isMulti ? $intents : [$primary->intent],
            'segments' => $isMulti ? $this->roughSegments($message) : [$message],
        ];
    }

    /**
     * @return list<string>
     */
    private function roughSegments(string $message): array
    {
        $parts = preg_split(
            '/\s*(?:,|،|و\s*بنفس\s*الوقت|وكمان|وبدي\s*كمان|and\s+also|also|وكذلك|وقلي|وقولي)\s*/ui',
            $message,
        ) ?: [$message];

        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));

        return $parts !== [] ? $parts : [$message];
    }
}
