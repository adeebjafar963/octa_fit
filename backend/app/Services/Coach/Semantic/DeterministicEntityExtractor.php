<?php

namespace App\Services\Coach\Semantic;

/**
 * Layer A — objective structured extraction (numbers, days, durations, units).
 */
class DeterministicEntityExtractor
{
    /** @var array<string, int> */
    private const ARABIC_ORDINALS = [
        'الاول' => 1, 'اول' => 1, 'first' => 1,
        'الثاني' => 2, 'ثاني' => 2, 'second' => 2,
        'التالت' => 3, 'التالتة' => 3, 'التالته' => 3, 'الثالث' => 3, 'ثالث' => 3, 'ثلاثة' => 3, 'ثلاثه' => 3, 'third' => 3,
        'الثانيه' => 2, 'الثانية' => 2,
        'الرابع' => 4, 'رابع' => 4, 'fourth' => 4,
        'الخامس' => 5, 'خامس' => 5, 'fifth' => 5,
        'السادس' => 6, 'سادس' => 6, 'sixth' => 6,
        'السابع' => 7, 'سابع' => 7, 'seventh' => 7,
    ];

    /**
     * @return array{
     *   day: int|null,
     *   duration_minutes: int|null,
     *   days_per_week: int|null,
     *   quantity_grams: float|null,
     *   discipline: string|null,
     *   equipment_mode: string|null,
     *   goal: string|null,
     *   body_part: string|null,
     *   is_nutrition_diet_context: bool
     * }
     */
    public function extract(string $message): array
    {
        $norm = $this->normalize($message);
        $nutritionDiet = $this->isNutritionDietContext($norm);

        return [
            'day' => $this->extractDay($norm),
            'duration_minutes' => $this->extractDurationMinutes($norm),
            'days_per_week' => $this->extractDaysPerWeek($norm),
            'quantity_grams' => $this->extractQuantityGrams($norm),
            // Diet/nutrition phrasing: تضخيم/تنشيف are nutrition goals, not training discipline.
            'discipline' => $nutritionDiet ? null : $this->detectDiscipline($norm),
            'equipment_mode' => $this->detectEquipment($norm),
            'goal' => $this->detectGoal($norm),
            'body_part' => $this->detectBodyPart($norm),
            'is_nutrition_diet_context' => $nutritionDiet,
        ];
    }

    public function isNutritionDietContext(string $norm): bool
    {
        return (bool) preg_match(
            '/(نظام\s*غذا|خطه\s*غذا|خطة\s*غذا|جدول\s*اكل|جدول\s*أكل|meal\s*plan|nutrition\s*plan|diet\s*plan|bulking\s*diet|cutting\s*diet|نظام\s*اكل|نظام\s*أكل|نظام\s*تضخيم|نظام\s*تنشيف|برنامج\s*اكل|برنامج\s*أكل)/u',
            $norm,
        );
    }

    public function detectBodyPart(string $norm): ?string
    {
        $map = [
            'chest' => '/(chest|\bpectoral|صدر)/u',
            'shoulders' => '/(shoulder|shoulders|اكتاف|أكتاف|كتف)/u',
            'back' => '/(\bback\b|ظهر)/u',
            'legs' => '/(legs?|quads?|ارجل|أرجل|رجلين|رجلان|رجليه|رجلي|رجل)/u',
            'arms' => '/(arms?|biceps?|triceps?|باي|تراي|ذراعين|ايدين|أيدين)/u',
            'abs' => '/(abs|core|بطن|كور)/u',
            'glutes' => '/(glutes?|مؤخره|مؤخرة)/u',
        ];
        foreach ($map as $part => $pat) {
            if (preg_match($pat, $norm)) {
                return $part;
            }
        }

        return null;
    }

    public function normalize(string $text): string
    {
        $lower = mb_strtolower(trim($text));
        $lower = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $lower) ?? $lower;
        $lower = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $lower);
        $lower = str_replace(['ة', 'ى'], ['ه', 'ي'], $lower);
        $lower = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower) ?? $lower;

        return trim(preg_replace('/\s+/u', ' ', $lower) ?? '');
    }

    private function extractDay(string $norm): ?int
    {
        if (preg_match('/\bday\s*(\d)\b/u', $norm, $m)) {
            return $this->clampDay((int) $m[1]);
        }
        if (preg_match('/(?:اليوم|يوم|day|session|workout|bout|حصة|حصه|جلسة|جلسه)\s*(?:number\s*)?(\d)/u', $norm, $m)) {
            return $this->clampDay((int) $m[1]);
        }
        if (preg_match('/(?:رقم|number)\s*(\d)/u', $norm, $m)) {
            return $this->clampDay((int) $m[1]);
        }
        // "session number three" / "workout number 3"
        if (preg_match('/(?:session|workout|day|bout|حصة|حصه|جلسة)\s+number\s+(\w+)/u', $norm, $m)) {
            $token = $m[1];
            if (ctype_digit($token)) {
                return $this->clampDay((int) $token);
            }
            if (isset(self::ARABIC_ORDINALS[$token])) {
                return self::ARABIC_ORDINALS[$token];
            }
        }
        foreach (self::ARABIC_ORDINALS as $word => $num) {
            $patterns = [
                '/(?:اليوم|يوم|day)\s*'.$word.'\b/u',
                '/(?:حصه|حصة|جلسه|جلسة|session|workout|bout|training\s*day)\s*'.$word.'\b/u',
                '/(?:رقم|number)\s*'.$word.'\b/u',
                '/\b'.$word.'\s*(?:يوم|اليوم|حصه|حصة|جلسه|جلسة|session|workout|bout|training\s*day)\b/u',
                '/باليوم\s*'.$word.'\b/u',
                '/on\s+day\s*'.$word.'\b/u',
                '/the\s+'.$word.'\s+(?:day|session|workout|bout|training\s*day)\b/u',
                '/\b'.$word.'\s+bout\b/u',
            ];
            foreach ($patterns as $pat) {
                if (preg_match($pat, $norm)) {
                    return $num;
                }
            }
        }

        return null;
    }

    private function extractDurationMinutes(string $norm): ?int
    {
        if (preg_match('/(\d{1,3})\s*(?:min|mins|minutes|دقيقه|دقيقة|دقائق)\b/u', $norm, $m)) {
            return min(180, max(15, (int) $m[1]));
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:hours?|hrs?|hr|ساعه|ساعة|ساعات)\b/u', $norm, $m)) {
            return min(180, max(15, (int) round(((float) $m[1]) * 60)));
        }
        if (preg_match('/\bساعتين\b|\btwo hours\b|\b2 hours\b|\bحوالي\s*ساعتين\b|\bتقريبا\s*ساعتين\b/u', $norm)) {
            return 120;
        }
        // Bare "ساعة" / "one hour" in modification contexts.
        if (preg_match('/\b(ساعه|ساعة|one\s*hour|an\s*hour)\b/u', $norm)) {
            return 60;
        }

        return null;
    }

    private function extractDaysPerWeek(string $norm): ?int
    {
        if (preg_match('/(\d)\s*(?:days?|ايام|أيام)\b/u', $norm, $m)) {
            return min(7, max(1, (int) $m[1]));
        }
        // "make it 5 days" / "اعملها 5 أيام"
        if (preg_match('/(?:make\s*it|اعملها|خليها|خلها)\s*(\d)\s*(?:days?|ايام|أيام)?/u', $norm, $m)) {
            return min(7, max(1, (int) $m[1]));
        }
        if (preg_match('/(?:من|for)\s*(\d)\s*(?:ايام|أيام|days?)/u', $norm, $m)) {
            return min(7, max(1, (int) $m[1]));
        }
        if (preg_match('/(\d)\s*(?:مرات|sessions?)\s*(?:بالاسبوع|بالأسبوع|per\s*week|a\s*week)?/u', $norm, $m)) {
            return min(7, max(1, (int) $m[1]));
        }
        if (preg_match('/(اربعة|أربعة|اربع|أربع)\s*(ايام|أيام|مرات|حصص)/u', $norm)) {
            return 4;
        }
        if (preg_match('/(خمسة|خمس)\s*(ايام|أيام|مرات|حصص)/u', $norm)) {
            return 5;
        }
        if (preg_match('/(ثلاثة|ثلاث)\s*(ايام|أيام|مرات|حصص)/u', $norm)) {
            return 3;
        }
        if (preg_match('/(ستة|ست)\s*(ايام|أيام|مرات|حصص)/u', $norm)) {
            return 6;
        }
        if (preg_match('/\bfour\s+days?\b/u', $norm)) {
            return 4;
        }
        if (preg_match('/\bfive[- ]day\b|\bfive\s+days?\b/u', $norm)) {
            return 5;
        }
        if (preg_match('/\bthree[- ]day\b|\bthree\s+days?\b|\bthrice\b/u', $norm)) {
            return 3;
        }
        if (preg_match('/(?:across|for|over)\s*(\d)\s*(?:weekly\s*)?(?:sessions?|days?|workouts?)/u', $norm, $m)) {
            return min(7, max(1, (int) $m[1]));
        }
        if (preg_match('/(four|five|three|six)\s+weekly\s+(?:sessions?|workouts?|days?)/u', $norm, $m)) {
            return match (strtolower($m[1])) {
                'three' => 3,
                'four' => 4,
                'five' => 5,
                'six' => 6,
                default => null,
            };
        }
        if (preg_match('/(three|four|five|six)\s+sessions?\s+weekly/u', $norm, $m)) {
            return match (strtolower($m[1])) {
                'three' => 3,
                'four' => 4,
                'five' => 5,
                'six' => 6,
                default => null,
            };
        }

        return null;
    }

    private function extractQuantityGrams(string $norm): ?float
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:g|grams?|غرام|جرام|غم|غ)\b/u', $norm, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private function detectDiscipline(string $norm): ?string
    {
        if (preg_match('/(mma|mixed martial|فنون\s*قتال|قتال\s*مختلط|قتالي|combat|striking)/u', $norm)) {
            return 'mma';
        }
        if (preg_match('/(calisthenics|كالستنيكس|كاليستنكس|باركور|وزن\s*الجسم)/u', $norm)) {
            return 'calisthenics';
        }
        if (preg_match('/(\bppl\b|push\s*pull\s*legs|upper\s*lower|full\s*body|bodybuilding|كمال\s*اجسام|كمال\s*أجسام|تضخيم|اضخم|أضخم|بناء\s*عضل|bulk|bulking|hypertroph|شد\s*جسم)/u', $norm)) {
            return 'bodybuilding';
        }
        if (preg_match('/(kicks?|punches?|striking|boxing|kickboxing|sparring)/u', $norm)) {
            return 'mma';
        }

        return null;
    }

    private function detectEquipment(string $norm): ?string
    {
        // Explicit home / no-gym / bodyweight wins over incidental gym mentions.
        $home = (bool) preg_match(
            '/(home|بالبيت|في\s*البيت|(^|[^\p{L}])بيت([^\p{L}]|$)|منزل|بيتي|at\s*home|بدون\s*معدات|ما\s*عندي\s*معدات|وزن\s*الجسم|bodyweight|no\s*equipment|no\s*weights|ما\s*عندي\s*اوزان|ما\s*عندي\s*أوزان|ما\s*عندي\s*جيم|without\s*equipment)/u',
            $norm,
        );
        // Note: Arabic \b is unreliable — match بالجيم/للجيم/بالنادي explicitly.
        // Do NOT treat bare "اجهزه/أجهزة" alone as gym-plan environment (comparison QA uses those words).
        $gym = (bool) preg_match(
            '/(gym|نادي|صاله|صالة|fitness\s*center|health\s*club|بالنادي|بالجيم|للجيم|(^|[^\p{L}])جيم([^\p{L}]|$)|(^|[^\p{L}])gym([^\p{L}]|$)|حديد|weights?|club)/u',
            $norm,
        )
            || ((bool) preg_match('/(اجهزه|أجهزة)/u', $norm) && (bool) preg_match('/(برنامج|خطه|خطة|تمرين|workout|gym|جيم|نادي)/u', $norm));
        $negatedGym = (bool) preg_match('/(ما\s*عندي\s*جيم|بدون\s*جيم|مو\s*بالجيم|not\s*gym|no\s*gym)/u', $norm);

        if ($home && (! $gym || $negatedGym)) {
            return 'home';
        }
        if ($gym && ! $negatedGym) {
            return 'gym';
        }
        if ($home) {
            return 'home';
        }

        return null;
    }

    private function detectGoal(string $norm): ?string
    {
        if (preg_match('/(bulk|bulking|hypertrophy|تضخيم|اضخم|أضخم|بناء\s*عضل|شد\s*جسم|ازيد\s*كتل|أزيد\s*كتل|زيد\s*كتله|زيد\s*كتلة|كتله\s*عضل|كتلة\s*عضل|muscle\s*gain)/u', $norm)) {
            return 'hypertrophy';
        }
        if (preg_match('/(cut|cutting|fat\s*loss|تنشيف)/u', $norm)) {
            return 'fat_loss';
        }
        if (preg_match('/(strength|قوه|قوة)/u', $norm)) {
            return 'strength';
        }

        return null;
    }

    private function clampDay(int $n): int
    {
        return min(7, max(1, $n));
    }
}
