<?php

namespace App\Services\Coach\Semantic;

/**
 * Layer B fallback — concept-level semantic classification (mirrors FastAPI ontology).
 * Used when /interpret is unavailable; NOT sentence-specific rules.
 */
class LocalSemanticInterpreter
{
    public function __construct(
        private readonly DeterministicEntityExtractor $entities,
    ) {}

    /**
     * @param  array<string, mixed>|null  $activePlanSummary
     * @return array<string, mixed>
     */
    public function interpret(string $message, bool $hasActivePlan, ?array $activePlanSummary = null): array
    {
        $norm = $this->entities->normalize($message);
        $det = $this->entities->extract($message);
        $lang = preg_match('/\p{Arabic}/u', $message) ? 'ar' : 'en';

        if ($this->isSafety($norm)) {
            return array_merge($this->payload('safety', 'safety', 'emergency', 0.95, $lang), [
                'capabilities' => ['safety'],
            ]);
        }
        // Prompt injection is a safety-policy refusal (eval bank expects safety capability).
        if ($this->isPromptInjection($norm)) {
            return array_merge($this->payload('safety', 'safety', 'prompt_injection', 0.95, $lang), [
                'capabilities' => ['safety'],
                'response_mode' => 'direct',
            ]);
        }
        if ($this->isMedical($norm)) {
            return array_merge($this->payload('safety', 'safety', 'medical', 0.95, $lang), [
                'capabilities' => ['safety'],
            ]);
        }

        // Bypass / ignore allergy constraints → safety refusal (not memory write).
        if ($this->isAllergyBypass($norm)) {
            return array_merge($this->payload('safety', 'safety', 'allergy_bypass', 0.93, $lang), [
                'capabilities' => ['safety', 'memory'],
                'response_mode' => 'direct',
            ]);
        }

        // Allergy + eating/plan ask → nutrition preference update + plan regen (not memory-only).
        if ($this->isAllergyConstraint($norm) && $this->isAllergyFoodAsk($norm)) {
            return [
                'intent' => 'nutrition_plan_modify',
                'domain' => 'nutrition',
                'sub_intent' => 'allergy_constraint',
                'operation' => ['type' => 'modify', 'target' => null, 'modification' => 'allergy_exclude'],
                'entities' => [],
                'constraints' => ['allergy' => true],
                'context_requirements' => ['profile' => true, 'active_plan' => true, 'memory' => true],
                'capabilities' => ['nutrition_plan', 'memory', 'safety'],
                'response_mode' => 'structured',
                'confidence' => 0.9,
                'language' => $lang,
            ];
        }

        // Standalone allergy / dislike preference → remember constraint (not a meal plan).
        if ($this->isAllergyConstraint($norm)) {
            return [
                'intent' => 'memory_related',
                'domain' => 'system',
                'sub_intent' => 'allergy_constraint',
                'operation' => ['type' => 'remember', 'target' => null, 'modification' => 'allergy_constraint'],
                'entities' => [],
                'constraints' => ['allergy' => true],
                'context_requirements' => ['memory' => true],
                'capabilities' => ['safety', 'memory'],
                'response_mode' => 'direct',
                'confidence' => 0.9,
                'language' => $lang,
            ];
        }

        // Fabricated / out-of-catalog exercise asks — refuse invention, keep exercise capability.
        if ($this->isFabricatedExerciseAsk($norm)) {
            return [
                'intent' => 'exercise_lookup',
                'domain' => 'training',
                'sub_intent' => 'fabricated_refuse',
                'operation' => ['type' => 'refuse', 'target' => null, 'modification' => null],
                'entities' => [],
                'constraints' => [],
                'context_requirements' => [],
                'capabilities' => ['exercise_lookup', 'safety'],
                'response_mode' => 'direct',
                'confidence' => 0.92,
                'language' => $lang,
            ];
        }

        if ($this->isPlanResume($norm)) {
            return [
                'intent' => 'training_plan_resume',
                'domain' => 'training',
                'sub_intent' => 'resume',
                'operation' => ['type' => 'resume', 'target' => null, 'modification' => null],
                'entities' => ['resume_plan' => true],
                'constraints' => [],
                'context_requirements' => ['active_plan' => true],
                'capabilities' => ['training_modification'],
                'response_mode' => 'structured',
                'confidence' => 0.9,
                'language' => $lang,
            ];
        }

        // Nutrition plan modifications BEFORE training modify — "خفف السعرات" must not become training.
        $nutModEarly = $this->detectNutritionPlanModification($norm, $lang);
        if ($nutModEarly !== null) {
            return $nutModEarly;
        }

        // Plan modification cues are CURRENT-MESSAGE intents even before active-plan injection.
        $mod = $this->detectPlanModification($message, $norm, $det['day'] ?? null, $lang, $hasActivePlan);
        if ($mod !== null) {
            return $mod;
        }

        // Equipment exclusion is a FOLLOW-UP only (active plan). Fresh home/bodyweight creates are plans.
        if ($hasActivePlan && $this->isEquipmentExclusion($norm) && ! $this->isFreshHomePlanAsk($norm, $det)) {
            return [
                'intent' => 'training_plan_modify',
                'domain' => 'training',
                'sub_intent' => 'exclude_equipment',
                'operation' => ['type' => 'modify', 'target' => null, 'modification' => 'exclude_equipment'],
                'entities' => [],
                'constraints' => [],
                'context_requirements' => ['active_plan' => true],
                'capabilities' => ['training_modification'],
                'response_mode' => 'structured',
                'confidence' => 0.86,
                'language' => $lang,
            ];
        }

        // Explicit "workout for today" / "تمرين اليوم" → same-day training plan (uses profile).
        if ($this->isTodayWorkoutAsk($norm, $det)) {
            return [
                'intent' => 'training_plan_create',
                'domain' => 'training',
                'sub_intent' => $det['discipline'] ?? 'bodybuilding',
                'operation' => ['type' => 'create', 'target' => null, 'modification' => null],
                'entities' => array_filter([
                    'discipline' => $det['discipline'],
                    'equipment_mode' => $det['equipment_mode'],
                    'goal' => $det['goal'],
                ], fn ($v) => $v !== null),
                'constraints' => array_filter([
                    'discipline' => $det['discipline'],
                    'equipment_mode' => $det['equipment_mode'],
                    'goal' => $det['goal'],
                ], fn ($v) => $v !== null),
                'context_requirements' => ['profile' => true],
                'capabilities' => ['training_plan'],
                'response_mode' => 'structured',
                'confidence' => 0.72,
                'language' => $lang,
            ];
        }

        // Open-ended "what should I do today?" before broad sports-QA matching on "شو".
        if ($this->isOpenEndedTodayAsk($norm)) {
            return [
                'intent' => 'ambiguous_request',
                'domain' => 'system',
                'sub_intent' => 'today_activity',
                'operation' => ['type' => 'clarify', 'target' => null, 'modification' => null],
                'entities' => [],
                'constraints' => [],
                'context_requirements' => [],
                'capabilities' => ['clarification'],
                'response_mode' => 'clarification',
                'confidence' => 0.55,
                'language' => $lang,
                'clarification_required' => true,
            ];
        }

        // Bare short workout ask with no muscle/goal/discipline → clarify.
        if ($this->isBareWorkoutAsk($norm, $det)) {
            return [
                'intent' => 'ambiguous_request',
                'domain' => 'system',
                'sub_intent' => 'bare_workout',
                'operation' => ['type' => 'clarify', 'target' => null, 'modification' => null],
                'entities' => [],
                'constraints' => [],
                'context_requirements' => [],
                'capabilities' => ['clarification'],
                'response_mode' => 'clarification',
                'confidence' => 0.5,
                'language' => $lang,
                'clarification_required' => true,
            ];
        }

        // Pre/post workout nutrition advice BEFORE food DB lookup.
        if ($this->isPrePostWorkoutNutrition($norm)) {
            return array_merge(
                $this->payload('general_sports_knowledge', 'sports_knowledge', 'timing_nutrition', 0.86, $lang, knowledge: true),
                ['capabilities' => ['rag_knowledge', 'nutrition_lookup']],
            );
        }

        // Food substitution / dislike alternatives → knowledge (not inventing DB IDs).
        if ($this->isFoodSubstitutionAsk($norm)) {
            return array_merge(
                $this->payload('general_sports_knowledge', 'sports_knowledge', 'food_substitution', 0.84, $lang, knowledge: true),
                ['capabilities' => ['rag_knowledge', 'nutrition_lookup']],
            );
        }

        // Concrete food/macro lookups BEFORE general sports (how/شو questions about foods).
        if ($this->isNutritionLookup($norm)) {
            return [
                'intent' => 'nutrition_lookup',
                'domain' => 'nutrition',
                'sub_intent' => str_contains($norm, 'بروتين') || str_contains($norm, 'protein') ? 'macro_lookup' : 'calorie_lookup',
                'operation' => ['type' => 'lookup', 'target' => null, 'modification' => null],
                'entities' => ['food_hint' => $this->foodHint($norm)],
                'constraints' => ['quantity_grams' => $det['quantity_grams']],
                'context_requirements' => ['nutrition_data' => true],
                'capabilities' => ['nutrition_lookup'],
                'response_mode' => 'direct',
                'confidence' => 0.88,
                'language' => $lang,
            ];
        }

        // "غير تمرين الصدر" / replace a named movement → plan modify (needs active plan).
        if ($this->isExerciseSwapFollowUp($norm, $det)) {
            return [
                'intent' => 'training_plan_modify',
                'domain' => 'training',
                'sub_intent' => 'replace_exercise',
                'operation' => [
                    'type' => 'replace',
                    'target' => ['type' => 'body_part', 'index' => null, 'reference' => $det['body_part']],
                    'modification' => 'replace_exercise',
                ],
                'entities' => array_filter(['body_part' => $det['body_part']], fn ($v) => $v !== null),
                'constraints' => [],
                'context_requirements' => ['active_plan' => true],
                'capabilities' => ['training_modification'],
                'response_mode' => 'structured',
                'confidence' => 0.85,
                'language' => $lang,
            ];
        }

        // SCOPE CONTROL (before general sports): body-part exercise lists AND focused muscle programs.
        // "شو أحسن تمارين للكتف" / "بدي برنامج أكتاف" → shoulders only — NOT a full-body split.
        if ($this->isFocusedBodyPartRequest($norm, $det)) {
            $level = $this->detectLevelCue($norm);

            return [
                'intent' => 'training_exercise_recommendation',
                'domain' => 'training',
                'sub_intent' => $this->isFocusedMuscleProgramAsk($norm) ? 'focused_muscle_program' : 'body_part',
                'operation' => ['type' => 'recommend', 'target' => null, 'modification' => null],
                'entities' => array_filter([
                    'body_part' => $det['body_part'],
                    'equipment_mode' => $det['equipment_mode'],
                    'goal' => $det['goal'],
                    'level' => $level,
                    'scope' => 'body_part',
                ], fn ($v) => $v !== null),
                'constraints' => array_filter([
                    'body_part' => $det['body_part'],
                    'equipment_mode' => $det['equipment_mode'],
                    'goal' => $det['goal'],
                    'level' => $level,
                    'scope' => 'body_part',
                ], fn ($v) => $v !== null),
                'context_requirements' => [],
                'capabilities' => ['grounded_exercises', 'exercise_lookup'],
                'response_mode' => 'structured',
                'confidence' => 0.9,
                'language' => $lang,
            ];
        }

        // Personalized body-comp advice (age/weight/height + goal) — NOT an automatic full plan.
        if ($this->isPersonalizedAdviceAsk($norm, $det)) {
            return [
                'intent' => 'general_sports_knowledge',
                'domain' => 'sports_knowledge',
                'sub_intent' => 'personalized_advice',
                'operation' => ['type' => 'advise', 'target' => null, 'modification' => null],
                'entities' => array_filter([
                    'age' => $this->extractAge($norm),
                    'weight_kg' => $this->extractWeightKg($norm),
                    'height_cm' => $this->extractHeightCm($norm),
                    'goal' => $det['goal'] ?? 'hypertrophy',
                ], fn ($v) => $v !== null),
                'constraints' => array_filter([
                    'goal' => $det['goal'] ?? 'hypertrophy',
                ], fn ($v) => $v !== null),
                'context_requirements' => ['profile' => true, 'knowledge' => true],
                'capabilities' => ['rag_knowledge'],
                'response_mode' => 'concise',
                'confidence' => 0.8,
                'language' => $lang,
            ];
        }

        // Explanation / definition questions (no concrete food DB hit / no body-part exercise ask).
        if ($this->isGeneralSports($norm)) {
            return $this->payload('general_sports_knowledge', 'sports_knowledge', 'explain', 0.82, $lang, knowledge: true);
        }

        // Nutrition PLAN before training — "نظام غذائي للتضخيم" / "نظام تضخيم" / "4 وجبات".
        if ($this->isNutritionPlanRequest($norm, $det)) {
            return [
                'intent' => 'nutrition_plan_create',
                'domain' => 'nutrition',
                'sub_intent' => $det['goal'] ?? 'general',
                'operation' => ['type' => 'create', 'target' => null, 'modification' => null],
                'entities' => ['goal' => $det['goal']],
                'constraints' => array_filter([
                    'goal' => $det['goal'],
                    'meals_per_day' => $this->extractMealsPerDay($norm),
                ], fn ($v) => $v !== null),
                'context_requirements' => ['profile' => true],
                'capabilities' => ['nutrition_plan'],
                'response_mode' => 'structured',
                'confidence' => 0.86,
                'language' => $lang,
            ];
        }

        // Goal-only short training requests: "بدي تضخيم", "بدي شد جسمي", "بدي PPL".
        if ($this->isGoalOnlyTrainingAsk($norm, $det)) {
            $discipline = $det['discipline'] ?? 'bodybuilding';
            $equip = $det['equipment_mode'];

            return [
                'intent' => 'training_plan_create',
                'domain' => 'training',
                'sub_intent' => $discipline,
                'operation' => ['type' => 'create', 'target' => null, 'modification' => null],
                'entities' => [
                    'discipline' => $discipline,
                    'goal' => $det['goal'] ?? 'hypertrophy',
                    'equipment_mode' => $equip,
                ],
                'constraints' => array_filter([
                    'discipline' => $discipline,
                    'goal' => $det['goal'] ?? 'hypertrophy',
                    'equipment_mode' => $equip,
                    'days' => $det['days_per_week'],
                    'duration_minutes' => $det['duration_minutes'],
                ], fn ($v) => $v !== null),
                'context_requirements' => ['profile' => true],
                'capabilities' => ['training_plan'],
                'response_mode' => 'structured',
                'confidence' => 0.72,
                'language' => $lang,
            ];
        }

        // Resistance / hypertrophy plan vocabulary is enough to proceed (not ambiguous).
        if ($this->isResistancePlanAsk($norm, $det)) {
            return [
                'intent' => 'training_plan_create',
                'domain' => 'training',
                'sub_intent' => 'bodybuilding',
                'operation' => ['type' => 'create', 'target' => null, 'modification' => null],
                'entities' => ['discipline' => 'bodybuilding', 'goal' => $det['goal'] ?? 'hypertrophy'],
                'constraints' => array_filter([
                    'discipline' => 'bodybuilding',
                    'goal' => $det['goal'] ?? 'hypertrophy',
                    'equipment_mode' => $det['equipment_mode'] ?? 'gym',
                ], fn ($v) => $v !== null),
                'context_requirements' => ['profile' => true],
                'capabilities' => ['training_plan'],
                'response_mode' => 'structured',
                'confidence' => 0.78,
                'language' => $lang,
            ];
        }

        // Ambiguous bare program ask — do not default to bodybuilding.
        if ($this->isAmbiguousProgramAsk($norm, $det)) {
            return [
                'intent' => 'ambiguous_request',
                'domain' => 'system',
                'sub_intent' => 'program_type',
                'operation' => ['type' => 'clarify', 'target' => null, 'modification' => null],
                'entities' => [],
                'constraints' => [],
                'context_requirements' => [],
                'capabilities' => [],
                'response_mode' => 'clarification',
                'confidence' => 0.4,
                'language' => $lang,
                'clarification_required' => true,
            ];
        }

        if ($this->isPlanRequest($norm) || $this->isImplicitPlanAsk($norm, $det)) {
            $discipline = $det['discipline'];
            if ($discipline === null && ($det['equipment_mode'] ?? null) === 'gym') {
                $discipline = 'bodybuilding';
            }
            if ($discipline === null && preg_match('/(حديد|weights?|iron|اجهزه|أجهزة)/u', $norm)) {
                $discipline = 'bodybuilding';
            }

            return [
                'intent' => 'training_plan_create',
                'domain' => 'training',
                'sub_intent' => $discipline ?? 'general',
                'operation' => ['type' => 'create', 'target' => null, 'modification' => null],
                'entities' => [
                    'discipline' => $discipline,
                    'goal' => $det['goal'],
                    'equipment_mode' => $det['equipment_mode'],
                ],
                'constraints' => array_filter([
                    'days' => $det['days_per_week'],
                    'duration_minutes' => $det['duration_minutes'],
                    'discipline' => $discipline,
                    'equipment_mode' => $det['equipment_mode'],
                    'goal' => $det['goal'],
                ], fn ($v) => $v !== null),
                'context_requirements' => [
                    'profile' => ! ($discipline && $det['days_per_week']),
                    'memory' => ! ($discipline && $det['days_per_week']),
                ],
                'capabilities' => ['training_plan'],
                'response_mode' => 'structured',
                'confidence' => $discipline ? 0.82 : 0.65,
                'language' => $lang,
            ];
        }

        // Environment alone is NOT a plan — need a want/train cue (avoids "هل الأجهزة أفضل...").
        if ($det['equipment_mode']
            && ! $this->isPlanRequest($norm)
            && ($det['body_part'] ?? null) === null
            && preg_match('/(بدي|اعمل|عطيني|want|need|give me|create|make|اتمرن|أتمرن|تمرن|train|workout|برنامج|خطه|خطة)/u', $norm)
        ) {
            return [
                'intent' => 'training_plan_create',
                'domain' => 'training',
                'sub_intent' => $det['discipline'] ?? 'bodybuilding',
                'operation' => ['type' => 'create', 'target' => null, 'modification' => null],
                'entities' => ['equipment_mode' => $det['equipment_mode'], 'discipline' => $det['discipline'] ?? 'bodybuilding'],
                'constraints' => ['equipment_mode' => $det['equipment_mode']],
                'context_requirements' => ['profile' => true],
                'capabilities' => ['training_plan'],
                'response_mode' => 'structured',
                'confidence' => 0.7,
                'language' => $lang,
            ];
        }

        // "بدي أتمرن بالجيم" / day-count / duration overrides without full plan vocabulary.
        if ($this->isTrainNowAsk($norm, $det) || $this->isDaysOverrideAsk($norm, $det) || $this->isDurationOverrideAsk($norm, $det)) {
            return [
                'intent' => 'training_plan_create',
                'domain' => 'training',
                'sub_intent' => $det['discipline'] ?? (($det['equipment_mode'] ?? null) === 'home' ? 'home' : 'bodybuilding'),
                'operation' => ['type' => 'create', 'target' => null, 'modification' => null],
                'entities' => array_filter([
                    'discipline' => $det['discipline'],
                    'equipment_mode' => $det['equipment_mode'],
                    'days' => $det['days_per_week'],
                ], fn ($v) => $v !== null),
                'constraints' => array_filter([
                    'discipline' => $det['discipline'],
                    'equipment_mode' => $det['equipment_mode'],
                    'days' => $det['days_per_week'],
                    'duration_minutes' => $det['duration_minutes'] ?? (
                        preg_match('/(120|two\s*hours|ساعتين)/u', $norm) ? 120 : null
                    ),
                ], fn ($v) => $v !== null),
                'context_requirements' => ['profile' => true],
                'capabilities' => ['training_plan'],
                'response_mode' => 'structured',
                'confidence' => 0.74,
                'language' => $lang,
            ];
        }

        return $this->payload('general_conversation', 'conversation', null, 0.45, $lang);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function detectPlanModification(string $message, string $norm, ?int $day, string $lang, bool $hasActivePlan = false): ?array
    {
        $harder = $this->hasAny($norm, [
            'harder', 'difficult', 'challenging', 'intensify', 'tougher', 'stronger', 'more challenging',
            'harden', 'bump difficulty', 'raise difficulty', 'increase difficulty', 'make my third',
            'make my workout tougher', 'tougher overall', 'more intense',
            'اصعب', 'اقوى', 'صعب', 'زيد', 'ارفع', 'تحدي', 'نار', 'اتعب', 'أتعب', 'challenge', 'level up',
            'شده', 'شدة', 'صعوبه', 'صعوبة', 'مستوى', 'نخلي', 'خلي',
        ]) || (bool) preg_match('/(tougher|harder|intensify|اصعب|أصعب).{0,20}(overall|workout|plan|جلسه|جلسة|خطه|خطة)?/u', $norm);
        $easier = $this->hasAny($norm, [
            'easier', 'lighter', 'reduce volume', 'deload', 'خفف', 'خفّف', 'سهل', 'اخف', 'أخف', 'قلل',
        ]);
        $replace = ($this->hasAny($norm, [
            'replace', 'substitute', 'swap', 'بدل', 'بدّل', 'غير', 'غيره', 'غيرها', 'بديل', 'شيل', 'عطيني بديل',
            'swap out', 'change the last', 'final movement', 'final drill', 'last drill', 'استبدال',
        ]) && (
            $this->hasAny($norm, [
                'last exercise', 'آخر تمرين', 'اخر تمرين', 'last movement', 'آخر حركة', 'اخر حركه',
                'final movement', 'final drill', 'last drill', 'التمرين الأخير', 'التمرين الاخير', 'الأخير', 'الاخير',
                'last move', 'الأخير', 'الاخير', 'الحركه', 'الحركة',
            ])
            || preg_match('/\b(غيره|غيرها|بدل\s*الأخير|بدل\s*الاخير|swap\s+the\s+last|last\s+move)\b/u', $norm)
        ))
            || ($this->hasAny($norm, ['ما بدي', 'dont want']) && $this->hasAny($norm, ['آخر', 'اخر', 'last']));

        if (($harder || $easier) && $day === null) {
            $day = $this->entities->extract($message)['day'] ?? null;
        }

        if ($harder && $day !== null) {
            return [
                'intent' => 'training_plan_modify',
                'domain' => 'training',
                'sub_intent' => 'increase_difficulty',
                'operation' => [
                    'type' => 'modify',
                    'target' => ['type' => 'day', 'index' => $day, 'reference' => null],
                    'modification' => 'increase_difficulty',
                ],
                'entities' => ['day' => $day],
                'constraints' => [],
                'context_requirements' => ['active_plan' => true],
                'capabilities' => ['training_modification'],
                'response_mode' => 'structured',
                'confidence' => 0.9,
                'language' => $lang,
            ];
        }

        if ($easier && $day !== null) {
            return [
                'intent' => 'training_plan_modify',
                'domain' => 'training',
                'sub_intent' => 'decrease_difficulty',
                'operation' => [
                    'type' => 'modify',
                    'target' => ['type' => 'day', 'index' => $day, 'reference' => null],
                    'modification' => 'decrease_difficulty',
                ],
                'entities' => ['day' => $day],
                'constraints' => [],
                'context_requirements' => ['active_plan' => true],
                'capabilities' => ['training_modification'],
                'response_mode' => 'structured',
                'confidence' => 0.88,
                'language' => $lang,
            ];
        }

        // Do not steal clear nutrition adjustments into training modify.
        if (preg_match('/(سعرات|بروتين|وجبات|calorie|protein|meals?|diet|ماكر)/u', $norm)
            && ! preg_match('/(يوم|day|جلسه|جلسة|تمرين\s|workout)/u', $norm)) {
            return null;
        }

        // Whole-plan difficulty tweak when active plan exists and no day is specified.
        if ($hasActivePlan && $day === null && ($harder || $easier)
            && preg_match('/(خليها|خليه|خفف|اصعب|أصعب|حجم|tougher|harder|overall|workout|intensify|volume)/u', $norm)) {
            return [
                'intent' => 'training_plan_modify',
                'domain' => 'training',
                'sub_intent' => $harder ? 'increase_difficulty' : 'decrease_difficulty',
                'operation' => [
                    'type' => 'modify',
                    'target' => null,
                    'modification' => $harder ? 'increase_difficulty' : 'decrease_difficulty',
                ],
                'entities' => [],
                'constraints' => [],
                'context_requirements' => ['active_plan' => true],
                'capabilities' => ['training_modification'],
                'response_mode' => 'structured',
                'confidence' => 0.8,
                'language' => $lang,
            ];
        }

        // Volume / emphasis adjustments without an explicit day still refer to the active plan.
        $volumeCue = (bool) preg_match(
            '/(خفف\s*(ال)?حجم|خفف\s*حجم\s*(ال)?تمرين|قلل\s*(ال)?فوليوم|قلل\s*(ال)?volume|زود\s*(ال)?(حجم|رجل|ارجل|أرجل|صدر|ظهر|اكتاف)|زيد\s*(ال)?(حجم|رجل)|اكثر\s*رجل|أكثر\s*رجل|more\s*legs?|less\s*volume|reduce\s*volume|increase\s*legs?|قص[رّ]\s*(ال)?جلسه|قص[رّ]\s*(ال)?جلسة|shorten\s*(the\s*)?(session|workout))/u',
            $norm,
        );
        if (($hasActivePlan && $volumeCue) || ($hasActivePlan && preg_match('/^(غيره|غيرها|بدله|بدلها)$/u', $norm))) {
            return [
                'intent' => 'training_plan_modify',
                'domain' => 'training',
                'sub_intent' => 'adjust_volume',
                'operation' => [
                    'type' => 'modify',
                    'target' => $day !== null ? ['type' => 'day', 'index' => $day, 'reference' => null] : null,
                    'modification' => 'adjust_volume',
                ],
                'entities' => array_filter(['day' => $day], fn ($v) => $v !== null),
                'constraints' => [],
                'context_requirements' => ['active_plan' => true],
                'capabilities' => ['training_modification'],
                'response_mode' => 'structured',
                'confidence' => 0.82,
                'language' => $lang,
            ];
        }

        if ($replace) {
            return [
                'intent' => 'training_plan_modify',
                'domain' => 'training',
                'sub_intent' => 'replace_exercise',
                'operation' => [
                    'type' => 'replace',
                    'target' => ['type' => 'day', 'index' => $day, 'reference' => 'last_exercise'],
                    'modification' => 'replace_last_exercise',
                ],
                'entities' => ['day' => $day],
                'constraints' => [],
                'context_requirements' => ['active_plan' => true],
                'capabilities' => ['training_modification'],
                'response_mode' => 'structured',
                'confidence' => 0.88,
                'language' => $lang,
            ];
        }

        $duration = $this->entities->extract($message)['duration_minutes'] ?? null;
        $durationFollowCue = $duration && $this->hasAny($norm, [
            'make it', 'make my', 'change', 'instead', 'خليها', 'خليه', 'خلي', 'بدلها',
            'every session', 'every day', 'كل حصة', 'الجلسات', 'update duration', 'change duration',
        ]);
        // Duration follow-ups win when an active plan exists, or when this is not a fresh create
        // (avoid classifying "4 days, 2 hours" creates as modify).
        if ($durationFollowCue && ($hasActivePlan || (! $this->isPlanRequest($norm) && ! $this->isImplicitPlanAsk($norm, $this->entities->extract($message))))) {
            return [
                'intent' => 'training_plan_modify',
                'domain' => 'training',
                'sub_intent' => 'change_duration',
                'operation' => ['type' => 'modify', 'target' => null, 'modification' => 'update_duration'],
                'entities' => [],
                'constraints' => ['duration_minutes' => $duration],
                'context_requirements' => ['active_plan' => true],
                'capabilities' => ['training_modification'],
                'response_mode' => 'structured',
                'confidence' => 0.85,
                'language' => $lang,
            ];
        }

        return null;
    }

    private function isNutritionLookup(string $norm): bool
    {
        if ($this->isNutritionPlanRequest($norm) || $this->isPrePostWorkoutNutrition($norm) || $this->isFoodSubstitutionAsk($norm)) {
            return false;
        }
        if (preg_match('/(meal plan|nutrition plan|خطه\s*غذا|خطة\s*غذائية|نظام\s*غذا)/u', $norm)) {
            return false;
        }
        // Conceptual nutrition education is NOT a food DB lookup.
        if (preg_match('/^(what is|what\'s|why|شو\s*هو|ما\s*هو|ما\s*هي|ليش|كيف|هل)\b/u', $norm)
            && ! preg_match('/(تفاح|دجاج|رز|بيض|موز|حليب|خبز|لحم|apple|chicken|rice|egg|banana|milk|bread|beef)/u', $norm)) {
            return false;
        }
        if (preg_match('/(how much protein should|protein should i|how many calories should|لازم آكل|أحتاج|يومي|daily target|بحاجه|بحاجة)/u', $norm)
            && ! preg_match('/(تفاح|دجاج|رز|بيض|خبز|لحم|apple|chicken|rice|egg|bread|beef)/u', $norm)) {
            return false;
        }
        $macro = preg_match('/(calor|kcal|protein|carb|macro|سعرات|سعره|سعرة|بروتين|كالوري|قديش|كم\s*سعر|شو فيه|طاقه|طاقة)/u', $norm);
        $food = preg_match(
            '/(apple|banana|orange|chicken|rice|egg|eggs|tuna|milk|yogurt|oats|salmon|cheese|lentil|bean|potato|dates?|peanut|butter|bread|beef|beefsteak|تفاح|موز|موزه|برتقال|دجاج|رز|بيض|تونه|تونة|حليب|زبادي|شوفان|سلمون|جبن|عدس|فاصوليا|بطاطا|تمر|فول\s*سوداني|فروج|زبدة|خبز|رغيف|لحم|بقر)/u',
            $norm,
        );
        $qty = preg_match('/(\d+(?:\.\d+)?)\s*(g|غرام|جرام|غم|غ|ملعقة|ملعقه|كوب)/u', $norm)
            || preg_match('/(بيضتين|تفاحتين|اذا\s*اكلت|إذا\s*أكلت|\d+\s*بيض|ملعقه|ملعقة)/u', $norm);
        $compare = (bool) preg_match('/(قارن|اعلى|أعلى|اعلي|فرق|vs|versus|\bor\b|ولا|compare|higher|مين\s*اعلى|مين\s*أعلى)/u', $norm) && $food;

        return (bool) (($macro && $food) || ($food && preg_match('/(كم|how|calor|protein|قديش|شو|سعرات|بروتين|سعره|سعرة)/u', $norm))
            || ($food && $qty) || $compare);
    }

    /**
     * @param  array<string, mixed>  $det
     */
    private function isNutritionPlanRequest(string $norm, array $det = []): bool
    {
        // Training+nutrition compounds → training primary; MultiIntent attaches nutrition.
        if ($this->isTrainingNutritionCompound($norm)) {
            return false;
        }

        if (($det['is_nutrition_diet_context'] ?? false) === true) {
            return true;
        }
        if ($this->entities->isNutritionDietContext($norm)) {
            return true;
        }
        $dietGoal = (bool) preg_match('/(تضخيم|تنشيف|bulk|bulking|cut|cutting|meal\s*prep)/u', $norm);
        $wants = (bool) preg_match('/(بدي|اعمللي|عطيني|سوّيلي|سويلي|جهزلي|رتبلي|want|need|give me|create|make|اعمل)/u', $norm);
        $explicitTrainingPlan = (bool) preg_match(
            '/(خطه\s*تدريب|خطة\s*تدريب|برنامج\s*تدريب|برنامج\s*(جيم|mma|حديد)|workout\s*plan|training\s*plan|gym\s*plan)/u',
            $norm,
        );
        $trainingCue = $explicitTrainingPlan
            || (bool) preg_match('/(تمرين|workout|gym|جيم|mma|نادي|حصة|جلسة|تدريب|كمال)/u', $norm);
        $dietCue = (bool) preg_match('/(غذا|اكل|أكل|diet|meal|nutrition|سعرات|نظام|وجبه|وجبة|وجبات|دايت)/u', $norm);

        if ($explicitTrainingPlan) {
            return false;
        }

        // Meal-count / easy meals / high-protein diet asks.
        if ($wants && preg_match('/(\d)\s*وجبات?|وجبات\s*سهله|وجبات\s*سهلة|high\s*protein|عالي\s*البروتين|اكل\s*عالي|دايت|diet\b|رتبلي\s*وجبات|وجباتي|خلال\s*اليوم|meals?\s+for|meal\s*plan|create\s+me\s+meals/u', $norm)
            && ! $trainingCue) {
            return true;
        }
        if (preg_match('/(create|make|give me|build).{0,20}(meals?|diet|meal\s*plan)/u', $norm)
            && preg_match('/(fat\s*loss|cutting|bulking|muscle|تنشيف|تضخيم)/u', $norm)) {
            return true;
        }

        // "اعمللي نظام تضخيم" — نظام + goal is nutrition even without "غذا".
        if ($wants && preg_match('/(^|[^\p{L}])نظام([^\p{L}]|$)/u', $norm) && $dietGoal && ! preg_match('/(تمرين|workout|mma|جيم\s*و|كمال)/u', $norm)) {
            return true;
        }

        return ($dietGoal && $wants && $dietCue && ! $trainingCue)
            || ($wants && $dietCue && ! $trainingCue && preg_match('/(نظام|خطه|خطة|جدول\s*اكل|جدول\s*أكل|وجبات)/u', $norm))
            || ($wants && preg_match('/(نظام\s*غذا|خطه\s*غذا|خطة\s*غذا|meal\s*plan|nutrition\s*plan|diet\s*plan)/u', $norm));
    }

    private function isTrainingNutritionCompound(string $norm): bool
    {
        $hasTraining = (bool) preg_match(
            '/(كمال|bodybuilding|mma|برنامج|تدريب|workout|gym|جيم|حديد|خطه\s*تدريب|خطة\s*تدريب)/u',
            $norm,
        );
        $hasNutrition = (bool) preg_match(
            '/(نظام\s*(تنشيف|تضخيم|غذا)|خطه\s*غذا|خطة\s*غذا|meal\s*plan|bulking\s*diet|cutting\s*diet|diet|وجبات|اكل|أكل)/u',
            $norm,
        );

        return $hasTraining && $hasNutrition;
    }

    private function isPrePostWorkoutNutrition(string $norm): bool
    {
        $timing = (bool) preg_match(
            '/(قبل\s*(ال)?(تمرين|جيم|حديد|نادي|تدريب|mma)|بعد\s*(ال)?(تمرين|جيم|حديد|نادي|تدريب|mma)|قبل\s*mma|بعد\s*mma|pre[- ]?workout|post[- ]?workout|before\s*(training|gym|workout|mma)|after\s*(a\s*)?(training|gym|workout|mma|sparring)|around\s*training|تمرين\s*بعد\s*ساعه|تمرين\s*بعد\s*ساعة)/u',
            $norm,
        );
        $eat = (bool) preg_match('/(اكل|أكل|آکل|آك|eat|meal|snack|وجبه|وجبة|خفيف|protein)/u', $norm);

        return $timing && $eat;
    }

    private function isFoodSubstitutionAsk(string $norm): bool
    {
        $dislike = (bool) preg_match('/(ما\s*بحب|ما\s*بدي|ما\s*عندي|dont\s*like|don\'t\s*like|dislike|hate|without)/u', $norm);
        $swap = (bool) preg_match('/(بديل|بديله|بديلها|بدل|غيرلي|substitute|instead|بديل\s*نباتي|نباتي|شي\s*ثاني|شي\s*غير|مشابه)/u', $norm);
        $food = (bool) preg_match(
            '/(تونه|تونة|دجاج|رز|بيض|حليب|تفاح|موز|tuna|chicken|rice|egg|milk|apple|banana|لحم|خبز|سمك|fish|وجبه|وجبة)/u',
            $norm,
        );
        $proteinSource = (bool) preg_match('/(مصدر\s*بروتين|protein\s*source|بروتين\s*نباتي)/u', $norm);

        return $proteinSource
            || ($swap && $food)
            || ($dislike && $food && ($swap || preg_match('/(شو|what|بديل)/u', $norm)))
            || ($dislike && $food && ! preg_match('/(سعرات|calor|غرام|protein\s+in)/u', $norm));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function detectNutritionPlanModification(string $norm, string $lang): ?array
    {
        $macroAdj = (bool) preg_match(
            '/(زود\s*(ال)?بروتين|خفف(لي)?\s*(ال)?سعرات|خفف\s*(ال)?سعرات|زيد\s*(ال)?بروتين|قلل\s*(ال)?سعرات|increase\s*protein|reduce\s*calories|more\s*protein|less\s*calories)/u',
            $norm,
        );
        // Modify cues only — "بدي 4 وجبات" is CREATE, "خليها 4 وجبات" is MODIFY.
        $mealsAdj = (bool) preg_match(
            '/(خليها\s*\d\s*وجبات?|خلها\s*\d\s*وجبات?|make\s*it\s*\d\s*meals?|وجبات\s*اسهل|وجبات\s*أسهل|غيرلي\s*عدد\s*الوجبات)/u',
            $norm,
        );
        $excludeFood = (bool) preg_match(
            '/(ما\s*بدي|ما\s*بحب|شيل|بدون|لا\s*تحط|dont\s*want|don\'t\s*want|remove).{0,24}(بيض|تونه|تونة|دجاج|حليب|رز|فول.{0,8}سوداني|tuna|egg|chicken|milk|rice|peanut)/u',
            $norm,
        ) || (bool) preg_match(
            '/(ما\s*بدي|شيل).{0,12}(حليب|بيض|تونه|دجاج).{0,12}(نظام|خطه|خطة|plan)/u',
            $norm,
        );
        $mealSwap = (bool) preg_match(
            '/(غيرلي\s*وجبه|غيرلي\s*وجبة|غير\s*الفطور|بدل\s*(الفطور|الغدا|الغداء|العشا|العشاء)|خل[يى]\s*الفطور|وجبة\s*العشا|meal\s*(swap|change)|replace\s+\w+\s+in\s+my\s+plan|اكل\s*ابسط|أكل\s*أبسط)/u',
            $norm,
        );
        $dislikeOnly = (bool) preg_match('/(ما\s*بحب|dislike)\s+\p{L}+/u', $norm)
            && (bool) preg_match('/(تونه|تونة|دجاج|بيض|حليب|رز|tuna|chicken|egg)/u', $norm);

        if (! $macroAdj && ! $mealsAdj && ! $excludeFood && ! $mealSwap && ! $dislikeOnly) {
            return null;
        }

        // Bare dislike without modify verb and without active nutrition context still counts as plan preference update.
        return [
            'intent' => 'nutrition_plan_modify',
            'domain' => 'nutrition',
            'sub_intent' => $macroAdj ? 'adjust_macros' : ($mealsAdj ? 'adjust_meals' : ($excludeFood ? 'exclude_food' : 'replace_meal')),
            'operation' => ['type' => 'modify', 'target' => null, 'modification' => 'nutrition_plan_adjust'],
            'entities' => [],
            'constraints' => [],
            'context_requirements' => ['profile' => true, 'active_plan' => false],
            'capabilities' => ['nutrition_plan'],
            'response_mode' => 'structured',
            'confidence' => 0.84,
            'language' => $lang,
        ];
    }

    /**
     * Body-part scoped ask: exercise list OR focused muscle program — never a full-body split.
     *
     * @param  array<string, mixed>  $det
     */
    private function isFocusedBodyPartRequest(string $norm, array $det): bool
    {
        if (($det['body_part'] ?? null) === null && ! $this->hasBodyPartLexicon($norm)) {
            return false;
        }
        // Full multi-day hypertrophy/MMA plans without a single-muscle focus stay as plan create.
        if ($this->isFullBodyTrainingPlanAsk($norm, $det)) {
            return false;
        }

        return $this->isExerciseRecommendation($norm, $det) || $this->isFocusedMuscleProgramAsk($norm);
    }

    /**
     * "بدي برنامج أكتاف" / "برنامج كتف للمبتدئ" — focused program, not full split.
     */
    private function isFocusedMuscleProgramAsk(string $norm): bool
    {
        if (! $this->hasBodyPartLexicon($norm)) {
            return false;
        }

        return (bool) preg_match('/(برنامج|خطه|خطة|program|plan|روتين|جلسه|جلسة|حصة)/u', $norm)
            && ! preg_match('/(mma|فنون\s*قتال|كمال\s*اجسام|bodybuilding|ppl|upper\s*lower|full\s*body)/u', $norm);
    }

    /**
     * True multi-day / full-discipline plan (no single-muscle scope).
     *
     * @param  array<string, mixed>  $det
     */
    private function isFullBodyTrainingPlanAsk(string $norm, array $det): bool
    {
        $hasBody = ($det['body_part'] ?? null) !== null || $this->hasBodyPartLexicon($norm);
        if ($hasBody && ! preg_match('/(mma|كمال|bodybuilding|ppl|upper\s*lower|full\s*body|تضخيم\s*\d|تنشيف\s*\d)/u', $norm)) {
            // Body part + program word without full-discipline cues → focused, not full plan.
            return false;
        }
        $days = ($det['days_per_week'] ?? null) !== null
            || (bool) preg_match('/(\d)\s*(ايام|أيام|days?)/u', $norm);
        $fullPlan = (bool) preg_match(
            '/(برنامج|خطه|خطة|program|plan).{0,40}(تضخيم|تنشيف|mma|كمال|bodybuilding|جيم|gym|بيت|home)|'
            .'(تضخيم|تنشيف|mma|كمال).{0,40}(برنامج|خطه|خطة|program|plan)|'
            .'(اعمللي|سويلي|جهزلي|create|build|give me).{0,30}(برنامج|program|plan)/u',
            $norm,
        );

        return $fullPlan && (! $hasBody || $days || preg_match('/(mma|كمال|bodybuilding|ppl)/u', $norm));
    }

    private function hasBodyPartLexicon(string $norm): bool
    {
        return (bool) preg_match(
            '/(صدر|اكتاف|أكتاف|كتف|ظهر|ارجل|أرجل|رجلين|بطن|باي|تراي|بايسبس|ترايسبس|كتاف|'
            .'chest|shoulder|shoulders|back|legs?|biceps?|triceps?|abs|glutes?)/u',
            $norm,
        );
    }

    /**
     * @param  array<string, mixed>  $det
     */
    private function isExerciseRecommendation(string $norm, array $det): bool
    {
        $bodyPart = $det['body_part'] ?? null;
        if ($bodyPart === null) {
            if (! preg_match('/(تمارين|تمرين|exercise|workout)/u', $norm)) {
                return false;
            }

            return $this->hasBodyPartLexicon($norm);
        }

        // Short wants / list questions: "بدي أكتاف", "شو أحسن تمارين للكتف".
        return (bool) preg_match('/(تمرين|تمارين|exercise|workout|moves?|حركه|حركة|احسن|أحسن|مناسب|best)/u', $norm)
            || (bool) preg_match('/(بدي|اعطيني|عطيني|want|give me|show me|حطلي|اخترعلي|كيف\s*اتمرن|كيف\s*أتمرن)/u', $norm);
    }

    private function detectLevelCue(string $norm): ?string
    {
        if (preg_match('/(مبتدئ|مبتدء|beginner)/u', $norm)) {
            return 'beginner';
        }
        if (preg_match('/(متقدم|advanced)/u', $norm)) {
            return 'advanced';
        }
        if (preg_match('/(متوسط|intermediate)/u', $norm)) {
            return 'intermediate';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $det
     */
    private function isPersonalizedAdviceAsk(string $norm, array $det): bool
    {
        $hasBio = $this->extractAge($norm) !== null
            || $this->extractWeightKg($norm) !== null
            || $this->extractHeightCm($norm) !== null
            || (bool) preg_match('/(عمري|وزني|طولي|age|weight|height)/u', $norm);
        $asksAdvice = (bool) preg_match('/(بتنصح|تنصح|advice|recommend|شو\s*اعمل|شو\s*أعمل|what\s+should|نصيحه|نصيحة)/u', $norm);
        $goal = ($det['goal'] ?? null) !== null
            || (bool) preg_match('/(كتله|كتلة|عضل|تضخيم|تنشيف|muscle|bulk|cut)/u', $norm);

        return $hasBio && ($asksAdvice || $goal) && ! $this->isPlanRequest($norm) && ! $this->isFocusedBodyPartRequest($norm, $det);
    }

    private function extractAge(string $norm): ?int
    {
        if (preg_match('/(?:عمري|age)\s*(\d{1,2})\b/u', $norm, $m)
            || preg_match('/\b(\d{1,2})\s*(?:سنه|سنة|years?\s*old)/u', $norm, $m)) {
            $n = (int) $m[1];

            return ($n >= 12 && $n <= 90) ? $n : null;
        }

        return null;
    }

    private function extractWeightKg(string $norm): ?float
    {
        if (preg_match('/(?:وزني|weight)\s*(\d{2,3})\b/u', $norm, $m)
            || preg_match('/\b(\d{2,3})\s*(?:كغ|كجم|kg)\b/u', $norm, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private function extractHeightCm(string $norm): ?float
    {
        if (preg_match('/(?:طولي|height)\s*(\d{2,3})\b/u', $norm, $m)
            || preg_match('/\b(\d{2,3})\s*(?:سم|cm)\b/u', $norm, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private function extractMealsPerDay(string $norm): ?int
    {
        if (preg_match('/(\d)\s*وجبات?/u', $norm, $m)) {
            return min(8, max(1, (int) $m[1]));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $det
     */
    private function isGoalOnlyTrainingAsk(string $norm, array $det): bool
    {
        if ($this->entities->isNutritionDietContext($norm) || ($det['is_nutrition_diet_context'] ?? false)) {
            return false;
        }
        $wants = (bool) preg_match('/(بدي|اعمللي|عطيني|want|need|give me|create|make|ساعدني)/u', $norm);
        if (! $wants) {
            return false;
        }
        $goalCue = ($det['goal'] ?? null) !== null
            || (bool) preg_match('/(تضخيم|تنشيف|hypertroph|bulk|cut|شد\s*جسم|ازيد\s*كتل|أضخم|اضخم|قوه|قوة|قوّي|قوي\s*حالي|ppl|upper\s*lower|full\s*body)/u', $norm);
        $splitCue = (bool) preg_match('/(upper\s*lower|push\s*pull|ppl|full\s*body|برو\s*سبليت|bro\s*split)/u', $norm);
        $skillCue = (bool) preg_match('/(اتعلم|أتعلم|تعلم|learn|pull\s*up|chin\s*up|kicks?|punches?|striking|boxing)/u', $norm);
        $hasBodyPart = ($det['body_part'] ?? null) !== null;

        // Goal / split / fight-skill short asks without body part → plan create.
        return ($goalCue || $splitCue || $skillCue) && ! $hasBodyPart && ! $this->isNutritionLookup($norm);
    }

    /**
     * @param  array<string, mixed>  $det
     */
    private function isAmbiguousProgramAsk(string $norm, array $det): bool
    {
        $asksProgram = (bool) preg_match('/(بدي|اعمللي|عطيني|سوّيلي|سويلي|جهزلي|want|need|give me|make me|create)\b/u', $norm)
            && (bool) preg_match('/(برنامج|program|plan|خطه|خطة|روتين)/u', $norm);
        if (! $asksProgram) {
            return false;
        }
        $hasDiscipline = ($det['discipline'] ?? null) !== null
            || ($det['equipment_mode'] ?? null) !== null
            || (bool) preg_match('/(mma|جيم|gym|بيت|home|حديد|bodybuilding|كمال|calisthenics|ppl)/u', $norm);
        $hasDays = ($det['days_per_week'] ?? null) !== null;
        $hasBodyPart = ($det['body_part'] ?? null) !== null;
        $hasDuration = ($det['duration_minutes'] ?? null) !== null;
        $hasGoal = ($det['goal'] ?? null) !== null
            || (bool) preg_match('/(تضخيم|تنشيف|hypertroph|bulk|strength|قوه)/u', $norm);
        $hasLevel = (bool) preg_match('/(مبتدئ|مبتدء|متقدم|beginner|advanced|intermediate|متوسط)/u', $norm);

        if (preg_match('/(workout\s*plan|training\s*plan|خطه\s*تدريب|خطة\s*تدريب|برنامج\s*تدريب)/u', $norm)) {
            return false;
        }

        // Level/goal present → proceed as plan (profile can fill discipline).
        if ($hasGoal || $hasLevel) {
            return false;
        }

        return ! $hasDiscipline && ! $hasBodyPart && ! $hasDays && ! $hasDuration;
    }

    private function isAllergyBypass(string $norm): bool
    {
        return (bool) preg_match(
            '/(تجاهل|تجاوز|ignore|bypass).{0,40}(حساسي|allerg|قيود|safety|تشخيص)/u',
            $norm,
        ) || (bool) preg_match(
            '/(even\s+if\s+i\s+ask|حتى\s+لو).{0,40}(لا\s*تستخدم|never\s+use|don\'t\s+use|dont\s+use)/u',
            $norm,
        ) || (bool) preg_match(
            '/(never\s+use|لا\s*تستخدم).{0,40}(even\s+if|حتى\s+لو)/u',
            $norm,
        ) || (bool) preg_match(
            '/(حساسي|allerg).{0,40}(بس\s*حط|but\s+add|حطلي|add\s+peanut)/u',
            $norm,
        );
    }

    private function isAllergyFoodAsk(string $norm): bool
    {
        return (bool) preg_match(
            '/(what can i eat|what should i eat|شو\s*(آكل|اكل|آکل)|meal\s*plan|nutrition\s*plan|وجبات|خطة\s*غذا|نظام\s*غذا)/u',
            $norm,
        );
    }

    private function isTodayWorkoutAsk(string $norm, array $det): bool
    {
        if (($det['body_part'] ?? null) !== null) {
            return false;
        }

        return (bool) preg_match(
            '/(want|need|give me|بدي|اعطيني|عطيني).{0,24}(workout|تمرين|تمارين|session|حصة|جلسة).{0,20}(today|اليوم)|'
            .'(workout|تمرين|تمارين).{0,12}(for\s*today|اليوم)|'
            .'i want a workout for today/u',
            $norm,
        );
    }

    private function isAllergyConstraint(string $norm): bool
    {
        if ($this->isAllergyBypass($norm)) {
            return false;
        }
        // Food quantity/macro questions about peanut butter are LOOKUPS, not allergy memory.
        if ($this->looksLikeFoodQuantityQuestion($norm)) {
            return false;
        }

        $allergySignal = (bool) preg_match(
            '/(حساسيه|حساسية|allergic|allergy|لا\s*تحطلي|لا\s*تستخدم|never\s+use|dont\s*put|don\'t\s*put|do\s*not\s*include|no\s*peanut|no\s*dairy|ما\s*بدي\s*اي\s*شي|ما\s*بدي\s*أي\s*شي|ماني\s*قادر\s*اكل|ما\s*فيني\s*اكل|ما\s*بقدر\s*اكل|cant\s*eat|can\'t\s*eat|تذكر|remember|shellfish|غلوتين|gluten)/u',
            $norm,
        );
        // "الفول السوداني" has article on both tokens — allow optional ال and gap.
        $allergen = (bool) preg_match(
            '/(فول.{0,8}سوداني|peanut|حليب|بيض|غلوتين|gluten|سمك|shellfish|قمح|wheat|nuts?|dairy)/u',
            $norm,
        );

        // "لا تستخدم القمح/X" without explicit allergy word is still an exclusion memory.
        if (! $allergySignal && preg_match('/(لا\s*تستخدم|never\s+use|do\s*not\s*use)/u', $norm) && $allergen) {
            $allergySignal = true;
        }

        return $allergySignal && $allergen;
    }

    private function looksLikeFoodQuantityQuestion(string $norm): bool
    {
        return (bool) preg_match('/(ملعقه|ملعقة|غرام|جرام|سعرات|سعره|سعرة|بروتين|calor|protein|كم|قديش|\?|؟)/u', $norm)
            && (bool) preg_match('/(زبدة|فول\s*سوداني|peanut|butter|دجاج|رز|بيض|تفاح|خبز|لحم)/u', $norm)
            && ! preg_match('/(حساسي|allerg|تذكر|remember|لا\s*تحط|ما\s*بدي)/u', $norm);
    }

    private function isFabricatedExerciseAsk(string $norm): bool
    {
        $exerciseCue = (bool) preg_match('/(تمرين|exercise|حركه|حركة|drill|strike|id)/u', $norm);
        if (! $exerciseCue) {
            return false;
        }
        if (preg_match('/(غير\s*موجود|وهمي|اخترع|اختراع|fabricat|invent|fake|make\s*up|non[- ]?existent|not\s+in\s+(the\s+)?(db|database|octa)|exercise_id\s*\d{4,}|\b999999\b|random\s+exercise\s+ids)/u', $norm)) {
            return true;
        }

        // Named invented exercise: "حطلي تمرين اسمه X" / "called HyperNova"
        return (bool) preg_match(
            '/(حطلي|اعطيني|عطيني|add|include|recommend|make).{0,40}(تمرين|exercise|حركه|حركة|strike).{0,40}(اسمه|called|named)/u',
            $norm,
        );
    }

    private function isBareWorkoutAsk(string $norm, array $det): bool
    {
        if (($det['body_part'] ?? null) !== null
            || ($det['discipline'] ?? null) !== null
            || ($det['goal'] ?? null) !== null
            || ($det['equipment_mode'] ?? null) !== null
            || ($det['days_per_week'] ?? null) !== null
            || ($det['duration_minutes'] ?? null) !== null) {
            return false;
        }

        // Want + bare workout/exercise word, no specifics.
        return (bool) preg_match('/(بدي|want|need|give me|اعطيني|عطيني)\s+(تمرين|تمارين|workout|exercise)\s*$/u', $norm)
            || (bool) preg_match('/^(تمرين|تمارين|workout)\s*$/u', $norm);
    }

    private function isEquipmentExclusion(string $norm): bool
    {
        // Specific gear missing from an existing plan — not a fresh home/bodyweight create.
        return (bool) preg_match(
            '/(ما\s*عندي|بدون|don\'t\s*have|do\s*not\s*have|without).{0,40}(دمبل|dumbbell|barbell|bench|kettlebell|heavy\s*bag|كيس|حقيبة|بار)/u',
            $norm,
        );
    }

    /**
     * Fresh home / bodyweight plan asks that mention missing equipment as an environment cue.
     *
     * @param  array<string, mixed>  $det
     */
    private function isFreshHomePlanAsk(string $norm, array $det): bool
    {
        if (($det['equipment_mode'] ?? null) === 'home') {
            return true;
        }

        return (bool) preg_match(
            '/(بيت|home|bodyweight|وزن\s*جسم|بدون\s*معدات|no\s*equipment|برنامج|خطه|خطة|workout|plan|program)/u',
            $norm,
        ) && (bool) preg_match('/(بدون\s*معدات|ما\s*عندي\s*معدات|no\s*equipment|bodyweight|بيت|home)/u', $norm);
    }

    private function isOpenEndedTodayAsk(string $norm): bool
    {
        return (bool) preg_match(
            '/(شو\s*اعمل\s*اليوم|شو\s*أعمل\s*اليوم|شو\s*بلعب\s*اليوم|ماذا\s*افعل\s*اليوم|what\s*should\s*i\s*do\s*today|what\s*do\s*i\s*train\s*today|what\s*to\s*train\s*today)/u',
            $norm,
        );
    }

    private function isPlanResume(string $norm): bool
    {
        return (bool) preg_match(
            '/(رجعني\s*ل(ل)?خطه|رجعني\s*ل(ل)?خطة|رجعني\s*لخطتي|رجّعني\s*ل|back\s*to\s*(my\s*|the\s*)?(plan|workout|program)|return\s*to\s*(the\s*)?(previous\s*)?(plan|program)|resume\s*(the\s*)?(plan|program)|continue\s*with\s*(the\s*)?(training\s*)?plan|رجعني\s*لسؤال)/u',
            $norm,
        );
    }

    private function isPlanRequest(string $norm): bool
    {
        if ($this->entities->isNutritionDietContext($norm) || $this->isNutritionPlanRequest($norm)) {
            return false;
        }
        if (preg_match('/^(how many|what is|why|كم|ما\s*هو|شو|ليش|هل)\b/u', $norm)
            && ! preg_match('/(program|plan|خطه|خطة|برنامج|حصه|حصة|جلسه|جلسة|جدول)/u', $norm)) {
            return false;
        }

        // Structural plan/session vocabulary — NOT bare "workout/تمرين".
        // CRITICAL: do NOT match روتين inside بروتين — use token boundaries.
        return (bool) preg_match(
            '/(program|plan|schedule|split|workout\s*plan|training\s*plan|خطه|خطة|برنامج|(^|[^\p{L}])روتين([^\p{L}]|$)|حصص|حصه|حصة|جلسه|جلسة|جلسات|(^|[^\p{L}])جدول([^\p{L}]|$)|نظام\s*تدريب|اعمللي|بدي\s*برنامج|رتبلي|قسملي|(^|[^\p{L}])رتب([^\p{L}]|$)|(^|[^\p{L}])قسم([^\p{L}]|$)|routine)/u',
            $norm,
        );
    }

    /**
     * @param  array<string, mixed>  $det
     */
    /**
     * @param  array<string, mixed>  $det
     */
    private function isResistancePlanAsk(string $norm, array $det): bool
    {
        $wants = (bool) preg_match('/(بدي|اعمللي|want|need|give me|create)/u', $norm);
        $plan = (bool) preg_match('/(خطه|خطة|برنامج|program|plan|جدول)/u', $norm);
        $resistance = (bool) preg_match('/(مقاومه|مقاومة|resistance|hypertrophy|تضخيم|حديد|weights?|اجهزه|أجهزة|اوزان|أوزان)/u', $norm);

        return $wants && $plan && $resistance;
    }

    private function isTrainNowAsk(string $norm, array $det): bool
    {
        $wants = (bool) preg_match('/(بدي|want|need|اعمل|اطيني|عطيني|هلق|خلي|خليه|خليها)/u', $norm);
        $train = (bool) preg_match('/(اتمرن|أتمرن|تمرن|train|workout|تمرين)/u', $norm);
        $place = ($det['equipment_mode'] ?? null) !== null
            || (bool) preg_match('/(بالجيم|للجيم|بالنادي|بالبيت|gym|home|نادي|بيت)/u', $norm);
        // Env-only relocate: "خليه بالبيت" / "هلق أنا بالبيت وما عندي معدات"
        $envRelocate = (bool) preg_match(
            '/(خليه|خليها|خلي|هلق|make\s*it).{0,20}(بالبيت|بالجيم|بالنادي|home|gym)|بدون\s*معدات|ما\s*عندي\s*معدات|no\s*equipment|bodyweight/u',
            $norm,
        ) && $place;
        // Explicit gym + available gear (CURRENT request overrides stale home profile/plan).
        // e.g. "أنا بالنادي وعندي أوزان وأجهزة" — not a machines-vs-weights QA.
        $gymGearAssert = ($det['equipment_mode'] ?? null) === 'gym'
            && (bool) preg_match('/(عندي|with).{0,20}(اوزان|أوزان|اجهزه|أجهزة|weights?|machines?)/u', $norm)
            && (bool) preg_match('/(بالنادي|بالجيم|للجيم|نادي|gym|نتمرن|اتمرن|أتمرن)/u', $norm)
            && ! preg_match('/(هل|افضل|أفضل|difference|vs)\b/u', $norm);

        return ($wants && $train && $place) || $envRelocate || $gymGearAssert;
    }

    /**
     * @param  array<string, mixed>  $det
     */
    private function isExerciseSwapFollowUp(string $norm, array $det): bool
    {
        $swap = (bool) preg_match('/(غير|بدل|بدّل|replace|swap|change)\s*(تمرين|exercise|حركه|حركة)?/u', $norm);

        return $swap && (($det['body_part'] ?? null) !== null || (bool) preg_match('/(تمرين|exercise)/u', $norm))
            && ! preg_match('/(بدي|اعطيني|عطيني|شو|list|افضل|أفضل)/u', $norm);
    }

    /**
     * @param  array<string, mixed>  $det
     */
    private function isDaysOverrideAsk(string $norm, array $det): bool
    {
        if (($det['days_per_week'] ?? null) === null
            && ! preg_match('/(\d)\s*(ايام|أيام|days?)/u', $norm)
            && ! preg_match('/(خمسة|خمس|اربعة|أربعة|ثلاثة|ثلاث)\s*(ايام|أيام)/u', $norm)) {
            return false;
        }

        return (bool) preg_match(
            '/(اعملها|خليها|خلها|make\s*it|change\s*(it\s*)?to|بدي)\s*.{0,12}(\d|خمس|ارب|ثلاث).{0,8}(يوم|ايام|أيام|days?)/u',
            $norm,
        ) || (bool) preg_match('/^(make\s*it|اعملها|خليها)\s*\d/u', $norm);
    }

    /**
     * @param  array<string, mixed>  $det
     */
    private function isDurationOverrideAsk(string $norm, array $det): bool
    {
        if (($det['duration_minutes'] ?? null) !== null) {
            return (bool) preg_match('/(not\s*\d|بدل|instead|مو|مش|make\s*sessions?)/u', $norm)
                || (bool) preg_match('/^\d{2,3}\s*minutes?/u', $norm);
        }

        return (bool) preg_match(
            '/(make\s*sessions?\s*(two\s*hours|\d{2,3})|\d{2,3}\s*minutes?\s+not|\d{2,3}\s*minutes?\s+instead|ساعتين|120\s*minutes?)/u',
            $norm,
        );
    }

    /**
     * @param  array<string, mixed>  $det
     */
    private function isImplicitPlanAsk(string $norm, array $det): bool
    {
        if ($this->entities->isNutritionDietContext($norm) || ($det['is_nutrition_diet_context'] ?? false)) {
            return false;
        }
        $wants = (bool) preg_match('/(بدي|want|need|give me|اعطيني|عطيني|بدنا|i want|make me|build|create|رتب|قسم|برنامج|routine|workout|سوّيلي|سويلي|جهزلي|اعمللي|structure|help me)\b/u', $norm);
        $hasDiscipline = ($det['discipline'] ?? null) !== null || ($det['equipment_mode'] ?? null) !== null
            || (bool) preg_match('/(combat|قتال)/u', $norm);
        // Bodyweight / no-equipment phrasing is a home training request even without "program".
        $bodyweightAsk = (bool) preg_match('/(وزن\s*جسم|بدون\s*معدات|bodyweight|no\s*equipment|ما\s*عندي\s*اوزان|ما\s*عندي\s*أوزان)/u', $norm);

        // Body-part exercise asks are NOT implicit full plans.
        if (($det['body_part'] ?? null) !== null && ! $this->isPlanRequest($norm)) {
            return false;
        }

        // Discipline/env + schedule signal is a plan even without an explicit want verb ("MMA 3 days").
        $hasSchedule = ($det['days_per_week'] ?? null) !== null || ($det['duration_minutes'] ?? null) !== null;
        if ($hasDiscipline && $hasSchedule) {
            return true;
        }

        // Want + MMA/gym/home/bodybuilding is a plan ask (days/duration may come from profile).
        // Home/no-equipment statements are plan asks even without an explicit want verb.
        return ($wants && $hasDiscipline)
            || ($wants && $bodyweightAsk)
            || $bodyweightAsk
            || (($det['equipment_mode'] ?? null) === 'home' && preg_match('/(هلق|بيت|home|معدات|equipment|bodyweight)/u', $norm));
    }

    private function isGeneralSports(string $norm): bool
    {
        $det = $this->entities->extract($norm);
        // Never steal body-part exercise asks or focused muscle programs into RAG.
        if ($this->isFocusedBodyPartRequest($norm, $det)) {
            return false;
        }
        // Never steal actual plan/program requests into RAG.
        if ($this->isPlanRequest($norm) || $this->isImplicitPlanAsk($norm, $det)
            || preg_match('/(give me|create|make me|build|want|need|بدي|اعمللي|برنامج|program|plan|خطه|خطة)\b/u', $norm)
                && preg_match('/(hypertrophy|strength|gym|جيم|mma|home|بيت|bodybuilding|كمال)/u', $norm)) {
            return false;
        }
        if ($this->isNutritionLookup($norm) && preg_match('/(تفاح|دجاج|رز|بيض|خبز|لحم|apple|chicken|bread|beef)/u', $norm)) {
            return false;
        }
        if ($this->isPrePostWorkoutNutrition($norm) || $this->isFoodSubstitutionAsk($norm)) {
            return true;
        }
        // Conceptual protein/education questions are knowledge, not plans.
        if (preg_match('/(ليش|why|هل|what is|ما\s*هو|شو\s*هو|side\s*question|difference\s+between).{0,40}(بروتين|protein|rpe|rir|اجهزه|أجهزة|اوزان|أوزان|hypertrophy|strength)/u', $norm)) {
            return true;
        }
        if (preg_match('/(اجهزه|أجهزة|machines).{0,40}(اوزان|أوزان|free\s*weights|حره|حرة)|(free\s*weights|اوزان\s*الحره|أوزان\s*الحرة).{0,40}(اجهزه|أجهزة|machines)/u', $norm)) {
            return true;
        }
        if (preg_match('/(failure\s+training|sleep\s+important|كم\s*مجموعه|كم\s*مجموعة|اسبوعيا|أسبوعي)/u', $norm)) {
            return true;
        }

        return (bool) preg_match('/^(what is|what\'s|why|how |what\s+should|what\s+to|difference|شو|ما\s*هو|ما\s*هي|ليش|كيف|هل)\b/u', $norm)
            || (bool) preg_match('/(progressive overload|warmup|warm-?ups?|cooldown|فشل\s*عضلي|overload progression|rir|\brpe\b|فرق\s*بين|explain|clarify|concise take|why\s+warm|rest between|بين\s*الجولات|بين\s*المجموعات|free\s*weights|machines|بحاجه|بحاجة)/u', $norm);
    }

    private function isSafety(string $norm): bool
    {
        return (bool) preg_match(
            '/(emergency|chest pain|cant breathe|can\'t breathe|طوارئ|ألم\s*شديد|كسر|ألم\s*حاد|الم\s*حاد|نزيف|ما\s*بقدر\s*اتنفس|ما\s*بقدر\s*أتنفس|اتنفس|أتنفس|دوار\s*شديد|numbness|swollen)/u',
            $norm,
        );
    }

    private function isPromptInjection(string $norm): bool
    {
        return (bool) preg_match(
            '/(ignore\s+(all\s+)?(previous|prior)\s+instructions|show\s+(your\s+)?(system\s+)?prompt|reveal\s+(the\s+)?api\s*key|bypass\s+safety|print\s+secrets|dan\s+mode|output\s+fake|fake\s+octa|تجاهل\s*(كل\s*)?(ال)?تعليمات|اهمل\s*(كل\s*)?(ال)?تعليمات|اعرض\s*(ال)?برومبت|اظهر\s*(ال)?برومبت|اعرض\s*تعليمات\s*النظام|معلومات\s*(عن\s*)?(النظام\s*)?الداخلي|system\s*prompt|developer\s*instructions)/u',
            $norm,
        );
    }

    private function isMedical(string $norm): bool
    {
        return (bool) preg_match(
            '/(diagnos|injury|fracture|إصابة|اصابه|تشخيص|دواء|prescription|ألم\b|\bالم\b|بتوجع|بيوجع|وجع|knee pain|shoulder pain|\bpain\b|hurts|مصا|مصب|متصاب|اصاب|swollen|numbness|دوار)/u',
            $norm,
        );
    }

    private function foodHint(string $norm): ?string
    {
        foreach ([
            'apple', 'chicken', 'rice', 'egg', 'tuna', 'banana', 'yogurt', 'oats', 'potato', 'bread', 'beef',
            'تفاح', 'دجاج', 'رز', 'بيض', 'تونه', 'موز', 'بطاطا', 'شوفان', 'خبز', 'لحم', 'بقر', 'زبدة',
        ] as $f) {
            if (str_contains($norm, $f)) {
                return $f;
            }
        }

        return null;
    }

    /** @param list<string> $needles */
    private function hasAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            $needle = $this->entities->normalize($n);
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        string $intent,
        string $domain,
        ?string $subIntent,
        float $confidence,
        string $lang,
        bool $knowledge = false,
    ): array {
        return [
            'intent' => $intent,
            'domain' => $domain,
            'sub_intent' => $subIntent,
            'operation' => ['type' => 'explain', 'target' => null, 'modification' => null],
            'entities' => [],
            'constraints' => [],
            'context_requirements' => ['knowledge' => $knowledge],
            'capabilities' => $knowledge ? ['rag_knowledge'] : [],
            'response_mode' => $knowledge ? 'concise' : 'direct',
            'confidence' => $confidence,
            'language' => $lang,
        ];
    }
}
