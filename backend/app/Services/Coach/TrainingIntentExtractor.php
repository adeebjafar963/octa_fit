<?php

namespace App\Services\Coach;

/**
 * Phase 11C + intelligence upgrade — structured intent from the CURRENT message first.
 * Profile / memory supply defaults only when the user did not specify a value.
 */
class TrainingIntentExtractor
{
    /**
     * True when the user is asking for a program / session schedule (not Q&A or bare exercise list).
     *
     * Compositional: schedule vocabulary OR (want + discipline/env + days/duration/frequency).
     */
    public function isPlanRequest(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        // Simple factual / definition questions — not plan creation.
        if (preg_match('/^(how many|what is|what\'s|why|متى|كم|ما هو|ما هي|شو هو|شو هي|شو يعني)\b/u', $lower)
            && ! preg_match('/\b(program|plan|routine|split|schedule|خطة|برنامج|حصة|جلسة|جدول)\b/u', $lower)) {
            return false;
        }

        // Explicit plan / session / schedule vocabulary (singular+plural, AR+EN).
        if (preg_match(
            '/\b(program|plan|routine|split|schedule|workout plan|training plan|bulking|cutting|hypertrophy|strength program|workout program|give me a .{0,40}(workout|program|plan)|create .{0,40}(workout|program|plan)|make .{0,40}(workout|program|plan)|خطة|برنامج|روتين|جدول|نظام\s*تدريب|حصة|حصص|جلسة|جلسات|تضخيم|تنشيف|فنون\s*قتالية|mixed martial)\b/u',
            $lower,
        )) {
            return true;
        }

        // Compositional: want/build + discipline or environment + schedule signal.
        $wants = (bool) preg_match(
            '/(بدي|أريد|ابني|اعمللي|اعطيني|رتب|رتبلي|قسم|قسملي|بدنا|أريد|want|need|give me|make me|build|create|plan my)\b/u',
            $lower,
        );
        $domain = $this->messageHasMma($lower)
            || $this->messageHasGym($lower)
            || $this->messageHasHome($lower)
            || (bool) preg_match('/(bodybuilding|كمال\s*أجسام|calisthenics|كاليستنكس|تدريب|اتدرب|أتدرب|أتمرن|اتمرن|workout|train)/u', $lower);
        $schedule = $this->detectExplicitDays($lower) !== null
            || $this->detectExplicitDurationMinutes($lower) !== null
            || (bool) preg_match('/(بالأسبوع|بالاسبوع|أسبوعي|اسبوعي|per\s*week|days?\s*a\s*week|مرات\s*بالأسبوع|حصص\s*أسبوع)/u', $lower);

        return $wants && $domain && $schedule;
    }

    /**
     * @param  array<string, mixed>  $userContext  Phase 11A payload
     * @return array<string, mixed>
     */
    public function extract(string $message, array $userContext = []): array
    {
        $lower = mb_strtolower($message);
        $isPlan = $this->isPlanRequest($message);

        $goalFromMessage = $this->detectGoalFromMessageOnly($lower);
        $disciplineFromMessage = $this->detectDisciplineFromMessageOnly($lower);
        $gymExplicit = $this->messageHasGym($lower);

        $goal = $goalFromMessage ?? $this->goalFromProfile($userContext);
        // Explicit gym without an explicit goal: do NOT inherit profile general_fitness.
        // Use a neutral gym hypertrophy template (engine requires a goal).
        if ($gymExplicit && $goalFromMessage === null && ! $this->messageHasMma($lower)) {
            $goal = 'hypertrophy';
        }
        $discipline = $disciplineFromMessage ?? $this->disciplineFromProfile($userContext, $goal);
        // Explicit MMA in message forces conditioning goal unless hypertrophy/strength also explicit.
        if ($disciplineFromMessage === 'mma' && $goalFromMessage === null) {
            $goal = 'conditioning';
        }
        if ($disciplineFromMessage === 'bodybuilding' && $goalFromMessage === null && ! $gymExplicit) {
            $goal = 'hypertrophy';
        }

        $level = $this->detectLevel($lower, $userContext);
        $explicitDays = $this->detectExplicitDays($lower);
        $days = $explicitDays ?? $this->daysFromProfileOrMemory($userContext);
        $explicitDuration = $this->detectExplicitDurationMinutes($lower);
        $duration = $explicitDuration ?? $this->durationFromProfileOrMemory($userContext);
        $split = $this->detectSplit($lower, $days, $goal, $level);
        $equipmentMode = $this->detectEquipmentMode($lower, $userContext, $discipline, $disciplineFromMessage !== null || $gymExplicit);

        return [
            'is_plan_request' => $isPlan,
            'intent' => $isPlan ? 'training_plan' : 'general',
            'domain' => 'training',
            'goal' => $goal,
            'discipline' => $discipline,
            'level' => $level,
            'days_per_week' => $days,
            'days_explicit' => $explicitDays !== null,
            'discipline_explicit' => $disciplineFromMessage !== null || $gymExplicit,
            'goal_explicit' => $goalFromMessage !== null,
            'duration_explicit' => $explicitDuration !== null,
            'preferred_split' => $split,
            'session_duration_minutes' => $duration,
            'equipment_mode' => $equipmentMode,
            'equipment' => $this->resolveEquipmentList($userContext, $equipmentMode, $discipline),
            'constraints' => [
                'injury_status' => $userContext['recovery']['injury_status']
                    ?? $userContext['injury_status']
                    ?? 'none',
                'injury_areas' => $userContext['recovery']['injury_areas']
                    ?? $userContext['injury_areas']
                    ?? [],
            ],
            'source' => [
                'from_profile_goal' => $goalFromMessage === null && ! ($gymExplicit && $goalFromMessage === null),
                'from_profile_level' => ! preg_match('/\b(beginner|intermediate|advanced|مبتدئ|متوسط|متقدم)\b/u', $lower),
                'from_profile_frequency' => $explicitDays === null,
                'from_profile_duration' => $explicitDuration === null,
                'from_profile_discipline' => $disciplineFromMessage === null && ! $gymExplicit,
            ],
            'defaults_used' => [],
            'confidence' => $this->estimateConfidence($disciplineFromMessage ?? ($gymExplicit ? 'bodybuilding' : null), $explicitDays, $explicitDuration, $isPlan),
            'gym_explicit' => $gymExplicit,
        ];
    }

    private function estimateConfidence(?string $discipline, ?int $days, ?int $duration, bool $isPlan): float
    {
        if (! $isPlan) {
            return 0.4;
        }
        $score = 0.55;
        if ($discipline !== null) {
            $score += 0.2;
        }
        if ($days !== null) {
            $score += 0.15;
        }
        if ($duration !== null) {
            $score += 0.1;
        }

        return min(1.0, round($score, 2));
    }

    private function goalFromProfile(array $userContext): string
    {
        $profileGoal = $userContext['goals']['primary_goal'] ?? $userContext['primary_goal'] ?? null;

        return match ($profileGoal) {
            'muscle_gain' => 'hypertrophy',
            'fat_loss' => 'fat_loss',
            'strength' => 'strength',
            'endurance' => 'endurance',
            'mma' => 'conditioning',
            'mobility' => 'mobility',
            'general_fitness' => 'general_fitness',
            default => 'hypertrophy',
        };
    }

    private function detectGoalFromMessageOnly(string $lower): ?string
    {
        if (preg_match('/\b(bulk|bulking|hypertrophy|muscle gain|gain muscle|تضخيم|بناء عضل|زيادة عضل)\b/u', $lower)) {
            return 'hypertrophy';
        }
        if (preg_match('/\b(cut|cutting|fat loss|lose fat|weight loss|تنشيف|خسارة دهون|حرق دهون)\b/u', $lower)) {
            return 'fat_loss';
        }
        if (preg_match('/\b(strength|powerlifting|قوة)\b/u', $lower)) {
            return 'strength';
        }
        // MMA / combat → conditioning goal (discipline handled separately).
        if ($this->messageHasMma($lower)) {
            return 'conditioning';
        }
        if (preg_match('/\b(conditioning|إعداد بدني)\b/u', $lower)) {
            return 'conditioning';
        }
        if (preg_match('/\b(endurance|cardio|تحمل)\b/u', $lower)) {
            return 'endurance';
        }
        if (preg_match('/\b(general fitness|fitness|لياقة عامة)\b/u', $lower)) {
            return 'general_fitness';
        }

        return null;
    }

    private function messageHasMma(string $lower): bool
    {
        return (bool) preg_match(
            '/\b(mma|mixed\s*martial\s*arts?|combat\s*sports?|striking|grappling)\b/u',
            $lower,
        ) || (bool) preg_match(
            '/فنون\s*قتالية(\s*مختلطة)?|فنون\s*القتال|مكافحة|قتال\s*مختلط|قتالي/u',
            $lower,
        );
    }

    private function detectDisciplineFromMessageOnly(string $lower): ?string
    {
        if ($this->messageHasMma($lower)) {
            return 'mma';
        }
        // Calisthenics / bodyweight — only when explicit (not mere "home").
        if (preg_match('/(calisthenics|كاليستنكس|كالستنيك|تمارين\s*وزن\s*الجسم|وزن\s*الجسم)/u', $lower)) {
            return 'home_calisthenics';
        }
        // Home workout (explicit home language, and not gym).
        if ($this->messageHasHome($lower) && ! $this->messageHasGym($lower)
            && ! preg_match('/(bodybuilding|كمال\s*أجسام|تضخيم)/u', $lower)) {
            return 'home_calisthenics';
        }
        // Bodybuilding / gym training environment → bodybuilding catalog (gym equipment).
        if (preg_match('/(bodybuilding|كمال\s*أجسام|hypertrophy|تضخيم)/u', $lower)
            || $this->messageHasGym($lower)) {
            return 'bodybuilding';
        }

        return null;
    }

    private function messageHasGym(string $lower): bool
    {
        // Latin gym / Arabic جيم / نادي / صالة — including clitics: للجيم، بالنادي، للصالة
        return (bool) preg_match('/\bgym\b/u', $lower)
            || (bool) preg_match('/(جيم|الجيم|للجيم|بالجيم)/u', $lower)
            || (bool) preg_match('/(نادي|النادي|للنادي|بالنادي)(\s*الرياضي)?/u', $lower)
            || (bool) preg_match('/(صالة|الصالة|للصالة|بالصالة)(\s*الرياضية)?/u', $lower);
    }

    private function messageHasHome(string $lower): bool
    {
        return (bool) preg_match(
            '/\b(home|bodyweight)\b/u',
            $lower,
        ) || (bool) preg_match(
            '/(منزلي|بالبيت|في\s*البيت|بالمنزل|في\s*المنزل|تمارين\s*منزلية)/u',
            $lower,
        );
    }

    private function disciplineFromProfile(array $userContext, string $goal): string
    {
        if ($goal === 'conditioning' && ($userContext['goals']['primary_goal'] ?? null) === 'mma') {
            return 'mma';
        }

        $location = $userContext['training']['training_location']
            ?? $userContext['training_location']
            ?? null;
        if ($location === 'home') {
            return 'home_calisthenics';
        }

        $types = $userContext['workout']['recent_training_types'] ?? [];
        if (is_array($types) && count($types) === 1 && in_array($types[0], ['bodybuilding', 'mma', 'home_calisthenics'], true)) {
            return $types[0];
        }

        return 'bodybuilding';
    }

    private function detectLevel(string $lower, array $userContext): string
    {
        if (preg_match('/\b(beginner|مبتدئ)\b/u', $lower)) {
            return 'beginner';
        }
        if (preg_match('/\b(advanced|متقدم)\b/u', $lower)) {
            return 'advanced';
        }
        if (preg_match('/\b(intermediate|متوسط)\b/u', $lower)) {
            return 'intermediate';
        }

        $level = $userContext['training']['fitness_level']
            ?? $userContext['fitness_level']
            ?? null;

        return in_array($level, ['beginner', 'intermediate', 'advanced'], true)
            ? $level
            : 'beginner';
    }

    private function daysFromProfileOrMemory(array $userContext): int
    {
        $freq = $userContext['training']['training_frequency_per_week']
            ?? $userContext['training_frequency_per_week']
            ?? null;
        if (is_numeric($freq)) {
            return min(6, max(2, (int) $freq));
        }

        foreach ($userContext['memory_context']['memories'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['category'] ?? '') === 'training_preference' && ($item['key'] ?? '') === 'days_per_week') {
                $days = $item['value']['days'] ?? null;
                if (is_numeric($days)) {
                    return min(6, max(2, (int) $days));
                }
            }
        }

        return 4;
    }

    private function detectExplicitDays(string $lower): ?int
    {
        if (preg_match('/\b([2-6])\s*[- ]?\s*day\b/u', $lower, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b([2-6])\s*(days|day)\b/u', $lower, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b([2-6])\s*أيام\b/u', $lower, $m)) {
            return (int) $m[1];
        }
        // Arabic word forms including clitics/hamza variants: من أربع أيام / وأربع ايام / أربع حصص
        if (preg_match('/(اربعة|أربعة|اربع|أربع)\s*(أيام|ايام|مرات|حصص)/u', $lower)) {
            return 4;
        }
        if (preg_match('/(خمسة|خمس)\s*(أيام|ايام|مرات|حصص)/u', $lower)) {
            return 5;
        }
        if (preg_match('/(ثلاثة|ثلاث)\s*(أيام|ايام|مرات|حصص)/u', $lower)) {
            return 3;
        }
        if (preg_match('/(ستة|ست)\s*(أيام|ايام|مرات|حصص)/u', $lower)) {
            return 6;
        }
        if (preg_match('/\b([2-6])\s*(مرات|sessions?)\b/u', $lower, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\bfour\s+days?\b/u', $lower)) {
            return 4;
        }
        if (preg_match('/(يومين|يومان)/u', $lower)) {
            return 2;
        }
        // Eastern Arabic digits
        if (preg_match('/([٢-٦])\s*(أيام|ايام)/u', $lower, $m)) {
            $map = ['٢' => 2, '٣' => 3, '٤' => 4, '٥' => 5, '٦' => 6];

            return $map[$m[1]] ?? null;
        }

        return null;
    }

    private function detectExplicitDurationMinutes(string $lower): ?int
    {
        // Hours first (EN + AR), including clitics: وساعتين / لساعتين
        if (preg_match('/ساعتين/u', $lower) || preg_match('/\b(two|2)\s*hours?\b/u', $lower)) {
            return 120;
        }
        if (preg_match('/ساعة\s*ونصف/u', $lower) || preg_match('/\b(one and a half|1\.5)\s*hours?\b/u', $lower)) {
            return 90;
        }
        if (preg_match('/ساعة/u', $lower) && ! preg_match('/ساعت/u', $lower)
            && preg_match('/(كل يوم|يوميا|يومياً|per day|every day|each day)/u', $lower)) {
            return 60;
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(hours?|hrs?|hr|ساعة|ساعات)\b/u', $lower, $m)) {
            return min(180, max(20, (int) round(((float) $m[1]) * 60)));
        }
        if (preg_match('/\b(\d{1,3})\s*(min|mins|minutes|دقيقة|دقائق)\b/u', $lower, $m)) {
            return min(180, max(20, (int) $m[1]));
        }
        if (preg_match('/\b(\d{1,3})\s*h\b/u', $lower, $m)) {
            return min(180, max(20, (int) $m[1] * 60));
        }

        return null;
    }

    private function durationFromProfileOrMemory(array $userContext): int
    {
        $dur = $userContext['training']['session_duration_minutes']
            ?? $userContext['session_duration_minutes']
            ?? null;
        if (is_numeric($dur)) {
            return min(180, max(20, (int) $dur));
        }

        foreach ($userContext['memory_context']['memories'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['category'] ?? '') === 'training_preference'
                && ($item['key'] ?? '') === 'session_duration_minutes') {
                $minutes = $item['value']['minutes'] ?? null;
                if (is_numeric($minutes)) {
                    return min(180, max(20, (int) $minutes));
                }
            }
        }

        return 60;
    }

    private function detectSplit(string $lower, int $days, string $goal, string $level): string
    {
        if (preg_match('/\b(full.?body|جسم كامل)\b/u', $lower)) {
            return 'full_body';
        }
        if (preg_match('/\b(upper.?lower|علوي.?سفلي)\b/u', $lower)) {
            return 'upper_lower';
        }
        if (preg_match('/\b(push.?pull.?legs|ppl)\b/u', $lower)) {
            return 'push_pull_legs';
        }
        if (preg_match('/\b(bro.?split|body.?part)\b/u', $lower)) {
            return 'body_part';
        }

        if ($days <= 3) {
            return 'full_body';
        }
        if ($days === 4) {
            return 'upper_lower';
        }
        if ($days === 5) {
            return $level === 'beginner' ? 'upper_lower' : 'body_part';
        }

        return 'push_pull_legs';
    }

    /**
     * @param  array<string, mixed>  $userContext
     */
    private function detectEquipmentMode(
        string $lower,
        array $userContext,
        string $discipline,
        bool $disciplineExplicit,
    ): string {
        if (preg_match('/\b(dumbbell|دمبل)\b/u', $lower)) {
            return 'dumbbell';
        }
        if ($this->messageHasGym($lower)) {
            return 'gym';
        }
        if ($this->messageHasHome($lower) || preg_match('/(calisthenics|كاليستنكس|كالستنيك)/u', $lower)) {
            return 'home';
        }

        // Explicit combat/MMA/bodybuilding/gym requests must not inherit home-only memory filters.
        if ($discipline === 'mma' || ($disciplineExplicit && $discipline === 'bodybuilding')) {
            return 'gym';
        }

        $fromMemory = $this->equipmentModeFromMemory($userContext);
        if ($fromMemory !== null) {
            return $fromMemory;
        }

        if ($discipline === 'home_calisthenics') {
            return 'home';
        }

        $location = $userContext['training']['training_location']
            ?? $userContext['training_location']
            ?? null;

        return $location === 'home' ? 'home' : 'gym';
    }

    /**
     * @param  array<string, mixed>  $userContext
     */
    private function equipmentModeFromMemory(array $userContext): ?string
    {
        $memories = $userContext['memory_context']['memories'] ?? [];
        if (! is_array($memories)) {
            return null;
        }

        foreach ($memories as $item) {
            if (! is_array($item) || ($item['category'] ?? '') !== 'equipment') {
                continue;
            }
            $value = $item['value'] ?? [];
            if (! is_array($value)) {
                continue;
            }
            $mode = $value['mode'] ?? null;
            if ($mode === 'dumbbell' || $mode === 'home' || $mode === 'gym') {
                return (string) $mode;
            }
            $items = $value['items'] ?? [];
            if (is_array($items)) {
                $joined = mb_strtolower(implode(' ', array_map('strval', $items)));
                if (str_contains($joined, 'dumbbell')) {
                    return 'dumbbell';
                }
                if (str_contains($joined, 'gym')) {
                    return 'gym';
                }
            }
        }

        foreach ($memories as $item) {
            if (! is_array($item) || ($item['category'] ?? '') !== 'training_preference') {
                continue;
            }
            if (($item['key'] ?? '') !== 'environment') {
                continue;
            }
            $text = is_array($item['value'] ?? null) ? ($item['value']['text'] ?? null) : null;
            if ($text === 'home') {
                return 'home';
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $userContext
     * @return list<string>
     */
    private function resolveEquipmentList(array $userContext, string $mode, string $discipline): array
    {
        if ($discipline === 'mma') {
            return []; // any MMA catalog equipment
        }

        $fromProfile = $userContext['training']['equipment'] ?? $userContext['equipment'] ?? [];
        if (! is_array($fromProfile)) {
            $fromProfile = [];
        }
        // Structured payload: { mode, available: [...] } vs flat string list.
        if (isset($fromProfile['available']) && is_array($fromProfile['available'])) {
            $fromProfile = $fromProfile['available'];
        } elseif (array_is_list($fromProfile) === false) {
            $fromProfile = [];
        }
        $fromProfile = array_values(array_filter(array_map(
            static fn ($v) => is_scalar($v) ? (string) $v : '',
            $fromProfile,
        )));

        if ($mode === 'home') {
            return $fromProfile !== [] ? $fromProfile : ['bodyweight'];
        }
        if ($mode === 'dumbbell') {
            return ['dumbbells', 'dumbbell'];
        }

        return $fromProfile;
    }
}
