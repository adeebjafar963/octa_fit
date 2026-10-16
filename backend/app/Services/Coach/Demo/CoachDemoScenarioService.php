<?php

namespace App\Services\Coach\Demo;

use App\Models\Exercise;
use App\Models\Food;
use App\Models\User;
use App\Services\Coach\CoachExerciseRetrievalService;
use App\Services\Coach\CoachPlanContextStore;
use App\Services\Coach\Knowledge\KnowledgeRetrievalService;
use App\Services\Coach\Nutrition\CalorieTargetCalculator;
use App\Services\Coach\Nutrition\MacroTargetCalculator;
use App\Services\Coach\Nutrition\NutritionPlanEngine;
use Illuminate\Support\Facades\Log;

/**
 * Isolated graduation-demo scenario layer.
 * Active only when config('services.ai.coach_demo_mode') is true.
 * Does NOT participate in normal Coach semantic/AI routing.
 */
class CoachDemoScenarioService
{
    public function __construct(
        private readonly CoachDemoScenarioResolver $resolver,
        private readonly CoachDemoSessionStore $demoSession,
        private readonly CoachPlanContextStore $planContextStore,
        private readonly CoachExerciseRetrievalService $exerciseRetrieval,
        private readonly NutritionPlanEngine $nutritionPlanEngine,
        private readonly CalorieTargetCalculator $calorieTargetCalculator,
        private readonly MacroTargetCalculator $macroTargetCalculator,
        private readonly KnowledgeRetrievalService $knowledgeRetrieval,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('services.ai.coach_demo_mode', false);
    }

    /**
     * @return array{
     *   message: string,
     *   grounded_exercises: list<array<string, mixed>>,
     *   training_plan: array<string, mixed>|null,
     *   nutrition: array<string, mixed>|null,
     *   nutrition_plan: array<string, mixed>|null,
     *   knowledge_context: array<string, mixed>|null
     * }|null
     */
    public function tryHandle(User $user, string $message, ?int $conversationId, string $lang = 'ar'): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $scenario = $this->resolver->resolve($message);
        if ($scenario === null) {
            return null;
        }

        Log::info('coach.demo_scenario.hit', [
            'scenario' => $scenario,
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
        ]);

        return match ($scenario) {
            1 => $this->scenarioPersonalizedAdvice($lang),
            2 => $this->scenarioCalorieProteinEstimates($lang),
            3 => $this->scenarioHypertrophyPlan($conversationId, $lang),
            4 => $this->scenarioShoulderExercises($lang),
            5 => $this->scenarioShoulderProgram($conversationId, $lang),
            6 => $this->scenarioMmaPlan($conversationId, $lang),
            7 => $this->scenarioHomePlan($conversationId, $lang),
            8 => $this->scenarioGymOverride($conversationId, $lang),
            9 => $this->scenarioOneSessionFullBody($conversationId, $lang),
            10 => $this->scenarioIntensifyDay3($conversationId, $lang),
            11 => $this->scenarioReplaceLastDay2($conversationId, $lang),
            12 => $this->scenarioReduceVolume($conversationId, $lang),
            13 => $this->scenarioSubstituteFocusExercise($conversationId, $lang),
            14 => $this->scenarioPostWorkoutNutrition($lang),
            15 => $this->scenarioPreWorkoutNutrition($lang),
            16 => $this->scenarioChickenSubstitute($lang),
            17 => $this->scenarioChickenProteinLookup($lang),
            18 => $this->scenarioCuttingNutrition($user, $message, $lang),
            19 => $this->scenarioEggSubstitutes($lang),
            20 => $this->scenarioChickenRiceCalc($lang),
            21 => $this->scenarioCuttingMacroDistribution($lang),
            22 => $this->scenarioSleepRecovery($message, $lang),
            23 => $this->scenarioBackExercises($lang),
            24 => $this->scenarioPromptInjectionRefusal($lang),
            25 => $this->scenarioCombinedHypertrophyGuidance($lang),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyKnowledge(string $query): array
    {
        return [
            'schema_version' => KnowledgeRetrievalService::SCHEMA_VERSION,
            'query' => $query,
            'topics' => [],
            'item_count' => 0,
            'items' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $grounded
     * @return array<string, mixed>
     */
    private function payload(
        string $message,
        array $grounded = [],
        ?array $trainingPlan = null,
        ?array $nutrition = null,
        ?array $nutritionPlan = null,
        string $query = '',
        ?array $knowledgeContext = null,
    ): array {
        return [
            'message' => $message,
            'grounded_exercises' => $grounded,
            'training_plan' => $trainingPlan,
            'nutrition' => $nutrition,
            'nutrition_plan' => $nutritionPlan,
            'knowledge_context' => $knowledgeContext ?? $this->emptyKnowledge($query),
        ];
    }

    /** @var array<string, int|null> */
    private array $idCache = [];

    private function idByName(string $nameEn): ?int
    {
        if (array_key_exists($nameEn, $this->idCache)) {
            return $this->idCache[$nameEn];
        }
        $id = Exercise::query()
            ->where('is_active', true)
            ->where('name_en', $nameEn)
            ->value('id');
        $this->idCache[$nameEn] = $id !== null ? (int) $id : null;

        return $this->idCache[$nameEn];
    }

    /**
     * @param  list<int|string>  $idsOrNames
     * @return list<Exercise>
     */
    private function loadExercises(array $idsOrNames): array
    {
        $ids = [];
        foreach ($idsOrNames as $ref) {
            if (is_int($ref) || (is_string($ref) && ctype_digit($ref))) {
                $ids[] = (int) $ref;
            } elseif (is_string($ref)) {
                $resolved = $this->idByName($ref);
                if ($resolved !== null) {
                    $ids[] = $resolved;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }
        $map = Exercise::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($map[$id])) {
                $ordered[] = $map[$id];
            }
        }

        return $ordered;
    }

    /**
     * @param  list<Exercise>  $exercises
     * @return list<array<string, mixed>>
     */
    private function groundedFrom(array $exercises, string $lang): array
    {
        $out = [];
        foreach ($exercises as $ex) {
            $out[] = $this->exerciseRetrieval->toGrounded($ex, $lang);
        }

        return $out;
    }

    private function displayName(Exercise $ex, string $lang): string
    {
        if ($lang === 'ar') {
            return $ex->name_ar ?: $ex->name_en;
        }

        return $ex->name_en ?: $ex->name_ar;
    }

    /**
     * @param  list<array{name:string,sets:int,reps:string,rest:int,cue:string}>  $specs
     * @return array{exercises: list<array<string, mixed>>, grounded: list<array<string, mixed>>, models: list<Exercise>}
     */
    private function buildExerciseBlocks(array $specs, string $lang): array
    {
        $names = array_column($specs, 'name');
        $models = $this->loadExercises($names);
        $byName = [];
        foreach ($models as $m) {
            $byName[$m->name_en] = $m;
        }

        $exercises = [];
        $groundedModels = [];
        foreach ($specs as $spec) {
            $ex = $byName[$spec['name']] ?? null;
            if ($ex === null) {
                continue;
            }
            $groundedModels[] = $ex;
            $exercises[] = [
                'exercise_id' => (int) $ex->id,
                'name' => $this->displayName($ex, $lang),
                'sets' => $spec['sets'],
                'reps' => $spec['reps'],
                'rest_seconds' => $spec['rest'],
                'notes' => $spec['cue'],
            ];
        }

        return [
            'exercises' => $exercises,
            'grounded' => $this->groundedFrom($groundedModels, $lang),
            'models' => $groundedModels,
        ];
    }

    /**
     * @param  list<array{day:int,title:string,exercises:list<array{name:string,sets:int,reps:string,rest:int,cue:string}>}>  $daySpecs
     * @return array{plan: array<string, mixed>, grounded: list<array<string, mixed>>}
     */
    private function buildPlan(
        string $title,
        string $goal,
        string $discipline,
        string $level,
        string $equipmentMode,
        int $durationMinutes,
        array $daySpecs,
        string $lang,
    ): array {
        $sessions = [];
        $allGrounded = [];
        $seen = [];
        foreach ($daySpecs as $day) {
            $block = $this->buildExerciseBlocks($day['exercises'], $lang);
            foreach ($block['grounded'] as $g) {
                $eid = (int) ($g['exercise_id'] ?? 0);
                if ($eid > 0 && ! isset($seen[$eid])) {
                    $seen[$eid] = true;
                    $allGrounded[] = $g;
                }
            }
            $sessions[] = [
                'day' => $day['day'],
                'title' => $day['title'],
                'focus' => $day['title'],
                'estimated_duration_minutes' => $durationMinutes,
                'exercises' => $block['exercises'],
            ];
        }

        $plan = [
            'schema_version' => '11c.1',
            'title' => $title,
            'goal' => $goal,
            'discipline' => $discipline,
            'level' => $level,
            'days_per_week' => count($sessions),
            'equipment_mode' => $equipmentMode,
            'requested_session_duration_minutes' => $durationMinutes,
            'estimated_session_duration_minutes' => $durationMinutes,
            'progression_strategy' => $lang === 'ar'
                ? 'زِد الحمل تدريجيًا كل أسبوع مع الحفاظ على تقنية صحيحة.'
                : 'Add load gradually each week while keeping solid technique.',
            'sessions' => $sessions,
        ];

        return ['plan' => $plan, 'grounded' => $allGrounded];
    }

    /**
     * @return array<string, mixed>
     */
    private function recallSession(?int $conversationId): array
    {
        if ($conversationId === null) {
            return [];
        }

        return $this->demoSession->recall($conversationId) ?? [];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function persistSession(?int $conversationId, array $patch): void
    {
        if ($conversationId === null) {
            return;
        }
        $existing = $this->recallSession($conversationId);
        $this->demoSession->remember($conversationId, array_merge($existing, $patch));
    }

    private function persistFollowUpPlan(?int $conversationId, array $plan, array $intent): void
    {
        if ($conversationId === null) {
            return;
        }
        $this->persistSession($conversationId, [
            'follow_up_plan' => $plan,
            'follow_up_intent' => $intent,
            'plan' => $plan,
            'intent' => $intent,
            'environment' => $intent['equipment_mode'] ?? ($plan['equipment_mode'] ?? 'gym'),
        ]);
        $this->planContextStore->remember($conversationId, $plan, $intent);
    }

    private function persistLastPlan(?int $conversationId, array $plan, array $intent): void
    {
        if ($conversationId === null) {
            return;
        }
        $this->persistSession($conversationId, [
            'plan' => $plan,
            'intent' => $intent,
            'environment' => $intent['equipment_mode'] ?? ($plan['equipment_mode'] ?? 'gym'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireFollowUpPlan(?int $conversationId, string $lang): array
    {
        $state = $this->recallSession($conversationId);
        if (is_array($state['follow_up_plan'] ?? null)) {
            return $state;
        }
        $this->scenarioHypertrophyPlan($conversationId, $lang);

        return $this->recallSession($conversationId);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $intent
     */
    private function persistFollowUpPlanUpdate(
        ?int $conversationId,
        array $plan,
        array $intent,
        ?int $focusExerciseId = null,
        ?string $focusExerciseName = null,
    ): void {
        $patch = [
            'follow_up_plan' => $plan,
            'follow_up_intent' => $intent,
            'plan' => $plan,
            'intent' => $intent,
        ];
        if ($focusExerciseId !== null) {
            $patch['focus_exercise_id'] = $focusExerciseId;
        }
        if ($focusExerciseName !== null) {
            $patch['focus_exercise_name'] = $focusExerciseName;
        }
        $this->persistSession($conversationId, $patch);
        $this->planContextStore->remember($conversationId, $plan, $intent);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{exercise_id:int,name:string,sets:int,reps:string,rest_seconds:int,notes:string}|null
     */
    private function defaultFocusExercise(array $plan): ?array
    {
        $sessions = $plan['sessions'] ?? [];
        $day2 = null;
        $day1 = null;
        foreach ($sessions as $session) {
            if ((int) ($session['day'] ?? 0) === 2) {
                $day2 = $session;
            }
            if ((int) ($session['day'] ?? 0) === 1) {
                $day1 = $session;
            }
        }
        foreach ([$day2, $day1] as $session) {
            if (! is_array($session)) {
                continue;
            }
            $exercises = $session['exercises'] ?? [];
            if ($exercises !== []) {
                $last = $exercises[count($exercises) - 1];

                return is_array($last) ? $last : null;
            }
        }

        return null;
    }

    private function formatTrainingMessage(array $plan, string $intro, string $lang): string
    {
        $lines = [$intro, ''];
        $lines[] = ($lang === 'ar' ? 'الهدف: ' : 'Goal: ').($plan['goal'] ?? '');
        $lines[] = ($lang === 'ar' ? 'التخصص: ' : 'Discipline: ').($plan['discipline'] ?? '');
        $lines[] = ($lang === 'ar' ? 'الأيام/أسبوع: ' : 'Days/week: ').($plan['days_per_week'] ?? '');
        if (isset($plan['requested_session_duration_minutes'])) {
            $lines[] = ($lang === 'ar' ? 'مدة الجلسة: ' : 'Session duration: ')
                .$plan['requested_session_duration_minutes'].($lang === 'ar' ? ' دقيقة' : ' min');
        }
        $lines[] = '';

        foreach ($plan['sessions'] ?? [] as $session) {
            $lines[] = 'Day '.($session['day'] ?? '?').' — '.($session['title'] ?? '');
            foreach ($session['exercises'] ?? [] as $ex) {
                $cue = ! empty($ex['notes']) ? ' — '.$ex['notes'] : '';
                $lines[] = '• '.($ex['name'] ?? 'Exercise').' (#'.$ex['exercise_id'].') — '
                    .($ex['sets'] ?? '?').' × '.($ex['reps'] ?? '?')
                    .' / راحة '.($ex['rest_seconds'] ?? '?').'ث'
                    .$cue;
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    /**
     * @return list<array{day:int,title:string,exercises:list<array{name:string,sets:int,reps:string,rest:int,cue:string}>}>
     */
    private function hypertrophyDaySpecs(): array
    {
        return [
            [
                'day' => 1,
                'title' => 'Chest + Triceps',
                'exercises' => [
                    ['name' => 'Barbell Bench Press', 'sets' => 4, 'reps' => '6-8', 'rest' => 120, 'cue' => 'تحكم بالنزول كاملًا'],
                    ['name' => 'Incline Dumbbell Press', 'sets' => 3, 'reps' => '8-10', 'rest' => 90, 'cue' => 'ميل خفيف للصدر العلوي'],
                    ['name' => 'Cable Chest Fly', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'عصر في نهاية الحركة'],
                    ['name' => 'Triceps Rope Pushdown', 'sets' => 3, 'reps' => '10-12', 'rest' => 60, 'cue' => 'الكوع ثابت بجانب الجسم'],
                    ['name' => 'Lying Triceps Extension (Skull Crusher)', 'sets' => 3, 'reps' => '8-10', 'rest' => 75, 'cue' => 'لا تفتح المرفقين للخارج'],
                ],
            ],
            [
                'day' => 2,
                'title' => 'Back + Biceps',
                'exercises' => [
                    ['name' => 'Conventional Deadlift', 'sets' => 3, 'reps' => '5', 'rest' => 150, 'cue' => 'ظهر محايد وشد اللاتس'],
                    ['name' => 'Barbell Bent-Over Row', 'sets' => 4, 'reps' => '6-8', 'rest' => 120, 'cue' => 'اسحب بالمرفق لا باليد'],
                    ['name' => 'Seated Cable Row', 'sets' => 3, 'reps' => '10-12', 'rest' => 75, 'cue' => 'صدر مفتوح نهاية السحب'],
                    ['name' => 'Barbell Curl', 'sets' => 3, 'reps' => '8-10', 'rest' => 60, 'cue' => 'بدون أرجحة'],
                    ['name' => 'Hammer Curl', 'sets' => 3, 'reps' => '10-12', 'rest' => 60, 'cue' => 'قبضة محايدة ثابتة'],
                ],
            ],
            [
                'day' => 3,
                'title' => 'Legs',
                'exercises' => [
                    ['name' => 'Barbell Back Squat', 'sets' => 4, 'reps' => '5-8', 'rest' => 150, 'cue' => 'نزول لعمق مريح وآمن'],
                    ['name' => 'Bulgarian Split Squat', 'sets' => 3, 'reps' => '8-10', 'rest' => 90, 'cue' => 'الجذع عمودي قدر الإمكان'],
                    ['name' => 'Goblet Squat', 'sets' => 3, 'reps' => '10-12', 'rest' => 75, 'cue' => 'كعب ثابت على الأرض'],
                    ['name' => '45-Degree Back Extension', 'sets' => 3, 'reps' => '10-12', 'rest' => 60, 'cue' => 'تحكم بالنزول'],
                ],
            ],
            [
                'day' => 4,
                'title' => 'Shoulders + Triceps',
                'exercises' => [
                    ['name' => 'Standing Overhead Press', 'sets' => 4, 'reps' => '6-8', 'rest' => 120, 'cue' => 'لا تقوّس أسفل الظهر'],
                    ['name' => 'Dumbbell Lateral Raise', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'رفع للجانب بدون أرجحة'],
                    ['name' => 'Cable Face Pull', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'اسحب نحو الوجه والكتف للخلف'],
                    ['name' => 'Overhead Cable Triceps Extension', 'sets' => 3, 'reps' => '10-12', 'rest' => 60, 'cue' => 'مدّ كامل مع تحكم'],
                ],
            ],
            [
                'day' => 5,
                'title' => 'Upper / Accessory',
                'exercises' => [
                    ['name' => 'Flat Dumbbell Press', 'sets' => 3, 'reps' => '8-10', 'rest' => 90, 'cue' => 'مدى حركة كامل'],
                    ['name' => 'Arnold Press', 'sets' => 3, 'reps' => '8-10', 'rest' => 90, 'cue' => 'دوران سلس للمعصم'],
                    ['name' => 'Reverse Pec-Deck Fly', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'ركز على الخلفي'],
                    ['name' => 'Incline Dumbbell Curl', 'sets' => 3, 'reps' => '10-12', 'rest' => 60, 'cue' => 'كتف ثابت على المقعد'],
                    ['name' => 'Dumbbell Overhead Extension', 'sets' => 3, 'reps' => '10-12', 'rest' => 60, 'cue' => 'مرفق قريب من الأذن'],
                ],
            ],
        ];
    }

    private function alternativeForExercise(string $nameEn): string
    {
        $map = [
            'Hammer Curl' => 'Incline Dumbbell Curl',
            'Barbell Curl' => 'Hammer Curl',
            'Incline Dumbbell Curl' => 'Hammer Curl',
            'Conventional Deadlift' => 'Seated Cable Row',
            'Barbell Bent-Over Row' => 'One-Arm Dumbbell Row',
            'Seated Cable Row' => 'Barbell Bent-Over Row',
            'Barbell Back Squat' => 'Goblet Squat',
            'Bulgarian Split Squat' => 'Goblet Squat',
            'Goblet Squat' => 'Bulgarian Split Squat',
            'Barbell Bench Press' => 'Flat Dumbbell Press',
            'Incline Dumbbell Press' => 'Flat Dumbbell Press',
            'Flat Dumbbell Press' => 'Incline Dumbbell Press',
            'Standing Overhead Press' => 'Seated Dumbbell Shoulder Press',
            'Seated Dumbbell Shoulder Press' => 'Arnold Press',
            'Triceps Rope Pushdown' => 'Overhead Cable Triceps Extension',
            'Lying Triceps Extension (Skull Crusher)' => 'Dumbbell Overhead Extension',
            'Cable Face Pull' => 'Reverse Pec-Deck Fly',
            'Reverse Pec-Deck Fly' => 'Cable Face Pull',
        ];

        return $map[$nameEn] ?? 'Cable Face Pull';
    }

    private function findFoodByNameEn(string $nameEn): ?Food
    {
        return Food::query()
            ->where('is_active', true)
            ->where('name_en', $nameEn)
            ->first();
    }

    // ── Scenarios ───────────────────────────────────────────────

    private function scenarioPersonalizedAdvice(string $lang): array
    {
        $msg = $lang === 'ar'
            ? <<<'TXT'
حسب معلوماتك (العمر 23، الوزن 70 كغ، الطول 175 سم، هدف زيادة الكتلة العضلية):

1) تدريب مقاومة 3–5 أيام/أسبوع مع تركيز على الحركات الأساسية (ضغط، سحب، قرفصاء، رفعة ميتة) وتقدّم تدريجي في الحمل أو التكرارات.
2) فائض سعرات خفيف تقريبًا +250 إلى +400 سعرة فوق الصيانة — لا مبالغة حتى لا تزيد الدهون بسرعة.
3) بروتين يومي تقريبًا 1.6–2.2 غ/كغ (حوالي 110–150 غ يوميًا لوزن 70 كغ) موزّع على الوجبات.
4) نوم 7–9 ساعات واستشفاء كافٍ بين جلسات نفس المجموعة العضلية.
5) اشرب ماء بانتظام (حوالي 2.5–3.5 لتر حسب نشاطك).

إذا حاب نبني برنامج أيام محدد، قلّي كم يوم بالأسبوع ومتاح جيم ولا بيت.
TXT
            : 'Personalized muscle-gain advice for age 23 / 70 kg / 175 cm: progressive resistance training, mild surplus, ~1.6–2.2 g/kg protein, sleep, hydration.';

        return $this->payload(trim($msg), [], null, null, null, 'personalized muscle advice');
    }

    private function scenarioCalorieProteinEstimates(string $lang): array
    {
        $context = [
            'weight_kg' => 70,
            'height_cm' => 175,
            'age' => 23,
            'training_frequency_per_week' => 4,
        ];
        $goal = 'muscle_gain';
        $calories = $this->calorieTargetCalculator->calculate($context, $goal);
        $target = (int) ($calories['calorie_target'] ?? 0);
        $macros = $this->macroTargetCalculator->calculate($target, $goal, $context);

        $tdee = $calories['tdee_kcal'] ?? null;
        $protein = $macros['protein_g'] ?? 0;
        $carbs = $macros['carbohydrates_g'] ?? 0;
        $fat = $macros['fat_g'] ?? 0;

        if ($lang === 'ar') {
            $msg = "تقدير تقريبي للتضخيم (عمر 23، وزن 70 كغ، طول 175 سم، هدف زيادة كتلة):\n\n";
            if ($tdee !== null) {
                $msg .= '• استهلاك يومي تقديري (TDEE): حوالي '.round((float) $tdee)." سعرة\n";
            }
            $msg .= "• سعرات مستهدفة تقريبية للتضخيم: حوالي {$target} سعرة/يوم (فائض خفيف فوق الصيانة)\n";
            $msg .= "• بروتين يومي تقديري: حوالي {$protein} غ\n";
            $msg .= "• كربوهيدرات تقديرية: حوالي {$carbs} غ\n";
            $msg .= "• دهون تقديرية: حوالي {$fat} غ\n\n";
            $msg .= 'هذه أرقام تقديرية وليست وصفة طبية — عدّل حسب تقدّمك وشعورك بالشبع والأداء.';
        } else {
            $msg = "Estimated hypertrophy targets (~70 kg, 175 cm, age 23): ~{$target} kcal/day, ~{$protein} g protein, ~{$carbs} g carbs, ~{$fat} g fat.";
        }

        return $this->payload(trim($msg), [], null, null, null, 'hypertrophy calorie protein estimate');
    }

    private function scenarioHypertrophyPlan(?int $conversationId, string $lang): array
    {
        $built = $this->buildPlan(
            $lang === 'ar' ? 'برنامج تضخيم 5 أيام — جيم' : '5-Day Hypertrophy Gym Program',
            'hypertrophy',
            'bodybuilding',
            'intermediate',
            'gym',
            70,
            $this->hypertrophyDaySpecs(),
            $lang,
        );

        $intent = [
            'discipline' => 'bodybuilding',
            'goal' => 'hypertrophy',
            'days_per_week' => 5,
            'equipment_mode' => 'gym',
            'session_duration_minutes' => 70,
            'is_plan_request' => true,
        ];
        $this->persistFollowUpPlan($conversationId, $built['plan'], $intent);

        $focus = $this->defaultFocusExercise($built['plan']);
        if ($focus !== null) {
            $this->persistSession($conversationId, [
                'focus_exercise_id' => (int) ($focus['exercise_id'] ?? 0),
                'focus_exercise_name' => (string) ($focus['name'] ?? ''),
            ]);
        }

        $intro = $lang === 'ar'
            ? "تم إنشاء برنامج تضخيم كامل لـ 5 أيام في الجيم اعتمادًا على تمارين OCTA FIT الموثقة.\nركّز على زيادة الحمل تدريجيًا ونوم جيد."
            : 'Created a complete 5-day hypertrophy gym program using grounded OCTA FIT exercises.';

        return $this->payload(
            $this->formatTrainingMessage($built['plan'], $intro, $lang),
            $built['grounded'],
            $built['plan'],
            null,
            null,
            'hypertrophy 5 day gym',
        );
    }

    private function scenarioShoulderExercises(string $lang): array
    {
        $specs = [
            ['name' => 'Seated Dumbbell Shoulder Press', 'sets' => 3, 'reps' => '8-10', 'rest' => 90, 'cue' => 'مبتدئ: وزن خفيف وتقنية ثابتة'],
            ['name' => 'Dumbbell Lateral Raise', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'لا ترفع فوق مستوى الكتف بقوة زائدة'],
            ['name' => 'Cable Face Pull', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'مهم لتوازن الكتف الخلفي'],
            ['name' => 'Reverse Pec-Deck Fly', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'حركة بطيئة وتحكم'],
            ['name' => 'Cable Lateral Raise', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'بديل كابل للجانبي'],
        ];
        $block = $this->buildExerciseBlocks($specs, $lang);

        $lines = [
            'تمارين كتف مناسبة للمبتدئ (كتف فقط — مو برنامج جسم كامل):',
            '',
        ];
        foreach ($block['exercises'] as $ex) {
            $lines[] = '• '.($ex['name']).' (#'.$ex['exercise_id'].') — '
                .$ex['sets'].' × '.$ex['reps'].' / راحة '.$ex['rest_seconds'].'ث'
                .' — '.($ex['notes'] ?? '');
        }
        $lines[] = '';
        $lines[] = 'ابدأ بأوزان تقدر تتحكم فيها، وزِد تدريجيًا مع تقنية صحيحة.';

        return $this->payload(implode("\n", $lines), $block['grounded'], null, null, null, 'beginner shoulder exercises');
    }

    private function scenarioShoulderProgram(?int $conversationId, string $lang): array
    {
        $specs = [
            ['name' => 'Seated Dumbbell Shoulder Press', 'sets' => 3, 'reps' => '8-10', 'rest' => 90, 'cue' => 'إحماء جيد للكتف أولًا'],
            ['name' => 'Dumbbell Lateral Raise', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'تحكم بدون أرجحة'],
            ['name' => 'Arnold Press', 'sets' => 3, 'reps' => '8-10', 'rest' => 90, 'cue' => 'وزن مبتدئ'],
            ['name' => 'Cable Face Pull', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'خلفي الكتف'],
            ['name' => 'Reverse Pec-Deck Fly', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'عزل خلفي'],
        ];
        $block = $this->buildExerciseBlocks($specs, $lang);

        $session = [
            'day' => 1,
            'title' => 'Focused Beginner Shoulders',
            'focus' => 'shoulders',
            'estimated_duration_minutes' => 40,
            'exercises' => $block['exercises'],
        ];

        $plan = [
            'schema_version' => '11c.1',
            'title' => 'برنامج أكتاف للمبتدئ (جلسة مركّزة)',
            'goal' => 'hypertrophy',
            'discipline' => 'bodybuilding',
            'level' => 'beginner',
            'days_per_week' => 1,
            'equipment_mode' => 'gym',
            'requested_session_duration_minutes' => 40,
            'sessions' => [$session],
            'scope' => 'body_part',
            'body_part' => 'shoulders',
        ];

        $intent = [
            'discipline' => 'bodybuilding',
            'goal' => 'hypertrophy',
            'days_per_week' => 1,
            'equipment_mode' => 'gym',
            'scope' => 'body_part',
            'body_part' => 'shoulders',
            'is_plan_request' => true,
        ];
        $this->persistLastPlan($conversationId, $plan, $intent);

        $intro = "برنامج أكتاف مركّز للمبتدئ — كتف فقط، بدون أرجل/صدر/ظهر أو سبليت جسم كامل.\n";

        return $this->payload(
            $this->formatTrainingMessage($plan, $intro, $lang),
            $block['grounded'],
            $plan,
            null,
            null,
            'focused shoulder program',
        );
    }

    private function scenarioMmaPlan(?int $conversationId, string $lang): array
    {
        $built = $this->buildPlan(
            $lang === 'ar' ? 'برنامج MMA — 4 أيام × ~ساعتين' : '4-Day MMA Program (~120 min)',
            'performance',
            'mma',
            'intermediate',
            'gym',
            120,
            [
                [
                    'day' => 1,
                    'title' => 'Striking + Combinations',
                    'exercises' => [
                        ['name' => 'Shadow Boxing Round', 'sets' => 6, 'reps' => '3 min', 'rest' => 60, 'cue' => 'إحماء ظل خفيف ثم إيقاع'],
                        ['name' => 'Jab-Cross (1-2) Combination', 'sets' => 8, 'reps' => '10 reps', 'rest' => 45, 'cue' => '1-2 نظيف'],
                        ['name' => 'Jab-Cross-Lead Hook (1-2-3) Combination', 'sets' => 6, 'reps' => '8 reps', 'rest' => 45, 'cue' => 'أنهّي بالهوك'],
                        ['name' => 'Heavy Bag Power Round', 'sets' => 6, 'reps' => '3 min', 'rest' => 60, 'cue' => 'قوة مضبوطة على الكيس'],
                        ['name' => 'Head Movement Round', 'sets' => 4, 'reps' => '2 min', 'rest' => 45, 'cue' => 'حركة رأس'],
                    ],
                ],
                [
                    'day' => 2,
                    'title' => 'Kicks / Knees + Clinch',
                    'exercises' => [
                        ['name' => 'Lead Teep (Push Kick)', 'sets' => 6, 'reps' => '8/side', 'rest' => 40, 'cue' => 'تييب دفع'],
                        ['name' => 'Rear Roundhouse Kick', 'sets' => 6, 'reps' => '8/side', 'rest' => 45, 'cue' => 'دوران الورك'],
                        ['name' => 'Low Calf Kick', 'sets' => 5, 'reps' => '10/side', 'rest' => 40, 'cue' => 'ضرب الساق السفلي'],
                        ['name' => 'Rear Straight Knee', 'sets' => 5, 'reps' => '8/side', 'rest' => 40, 'cue' => 'ركبة مستقيمة'],
                        ['name' => 'Clinch Knee Strikes', 'sets' => 4, 'reps' => '45s', 'rest' => 45, 'cue' => 'كلينش آمن'],
                    ],
                ],
                [
                    'day' => 3,
                    'title' => 'Strength + Conditioning',
                    'exercises' => [
                        ['name' => 'Push-Up', 'sets' => 4, 'reps' => '12-15', 'rest' => 60, 'cue' => 'قوة دفع'],
                        ['name' => 'Chin-Up', 'sets' => 4, 'reps' => '6-8', 'rest' => 90, 'cue' => 'سحب'],
                        ['name' => 'Bodyweight Squat', 'sets' => 4, 'reps' => '15', 'rest' => 60, 'cue' => 'أرجل'],
                        ['name' => 'Sprawl', 'sets' => 6, 'reps' => '8', 'rest' => 40, 'cue' => 'سبراول دفاعي'],
                        ['name' => 'Plyometric Push-Up', 'sets' => 4, 'reps' => '8', 'rest' => 60, 'cue' => 'انفجار'],
                    ],
                ],
                [
                    'day' => 4,
                    'title' => 'Movement / Defense + Recovery',
                    'exercises' => [
                        ['name' => 'Slip Left and Right', 'sets' => 5, 'reps' => '45s', 'rest' => 30, 'cue' => 'زحلقة'],
                        ['name' => 'Roll Under the Hook', 'sets' => 5, 'reps' => '45s', 'rest' => 30, 'cue' => 'لف تحت الهوك'],
                        ['name' => 'High Guard Shell', 'sets' => 4, 'reps' => '60s', 'rest' => 30, 'cue' => 'حراسة عالية'],
                        ['name' => 'Check the Low Kick', 'sets' => 5, 'reps' => '8/side', 'rest' => 40, 'cue' => 'صد الركلة'],
                        ['name' => 'Towel Shoulder Dislocate', 'sets' => 3, 'reps' => '10', 'rest' => 45, 'cue' => 'موبيليتي كتف'],
                    ],
                ],
            ],
            $lang,
        );

        $intent = [
            'discipline' => 'mma',
            'days_per_week' => 4,
            'session_duration_minutes' => 120,
            'equipment_mode' => 'gym',
            'is_plan_request' => true,
        ];
        $this->persistLastPlan($conversationId, $built['plan'], $intent);

        $intro = 'تم إنشاء برنامج MMA لـ 4 أيام، كل يوم حوالي ساعتين — مهارات ضرب، ركل، قوة، وحركة/دفاع.';

        return $this->payload(
            $this->formatTrainingMessage($built['plan'], $intro, $lang),
            $built['grounded'],
            $built['plan'],
            null,
            null,
            'mma 4 day 120',
        );
    }

    private function scenarioHomePlan(?int $conversationId, string $lang): array
    {
        $built = $this->buildPlan(
            'برنامج بيت بدون معدات',
            'general_fitness',
            'calisthenics',
            'beginner',
            'home',
            45,
            [
                [
                    'day' => 1,
                    'title' => 'Full Body A',
                    'exercises' => [
                        ['name' => 'Bodyweight Squat', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'وزن الجسم'],
                        ['name' => 'Knee Push-Up', 'sets' => 3, 'reps' => '8-12', 'rest' => 60, 'cue' => 'على الركب إن لزم'],
                        ['name' => 'Plank Shoulder Tap', 'sets' => 3, 'reps' => '20 taps', 'rest' => 45, 'cue' => 'جذع ثابت'],
                        ['name' => 'Pike Push-Up', 'sets' => 3, 'reps' => '6-10', 'rest' => 60, 'cue' => 'كتف/دفع'],
                    ],
                ],
                [
                    'day' => 2,
                    'title' => 'Full Body B',
                    'exercises' => [
                        ['name' => 'Chair Bulgarian Split Squat', 'sets' => 3, 'reps' => '8/side', 'rest' => 60, 'cue' => 'كرسي ثابت'],
                        ['name' => 'Standard Push-Up', 'sets' => 3, 'reps' => '8-12', 'rest' => 60, 'cue' => 'جسم مستقيم'],
                        ['name' => 'Towel Face Pull', 'sets' => 3, 'reps' => '12-15', 'rest' => 45, 'cue' => 'منشفة'],
                        ['name' => 'Push-Up', 'sets' => 3, 'reps' => '10-15', 'rest' => 60, 'cue' => 'دفع منزلي'],
                    ],
                ],
                [
                    'day' => 3,
                    'title' => 'Conditioning + Core',
                    'exercises' => [
                        ['name' => 'Squat Jump', 'sets' => 3, 'reps' => '10', 'rest' => 60, 'cue' => 'هبوط هادئ'],
                        ['name' => 'Wall Push-Up', 'sets' => 3, 'reps' => '10-15', 'rest' => 45, 'cue' => 'نسخة أسهل'],
                        ['name' => 'Lateral Lunge', 'sets' => 3, 'reps' => '8/side', 'rest' => 45, 'cue' => 'توازن'],
                        ['name' => 'Towel Shoulder Dislocate', 'sets' => 2, 'reps' => '8', 'rest' => 45, 'cue' => 'موبيليتي'],
                    ],
                ],
            ],
            $lang,
        );

        $intent = [
            'discipline' => 'calisthenics',
            'equipment_mode' => 'home',
            'days_per_week' => 3,
            'is_plan_request' => true,
        ];
        $this->persistLastPlan($conversationId, $built['plan'], $intent);

        $intro = 'برنامج مناسب للبيت وبدون معدات — تمارين وزن جسم فقط، بدون أجهزة جيم.';

        return $this->payload(
            $this->formatTrainingMessage($built['plan'], $intro, $lang),
            $built['grounded'],
            $built['plan'],
            null,
            null,
            'home no equipment',
        );
    }

    private function scenarioGymOverride(?int $conversationId, string $lang): array
    {
        $state = $this->recallSession($conversationId);
        $plan = is_array($state['plan'] ?? null) ? $state['plan'] : null;

        $intent = array_merge($state['intent'] ?? [], ['equipment_mode' => 'gym']);
        if ($plan !== null) {
            $plan['equipment_mode'] = 'gym';
            $this->persistLastPlan($conversationId, $plan, $intent);
        } else {
            $this->persistSession($conversationId, [
                'intent' => $intent,
                'environment' => 'gym',
            ]);
        }

        $msg = <<<'TXT'
تمام — حدّثنا السياق الحالي إلى الجيم مع أوزان وأجهزة.

من هلق راح نعتمد تمارين متوافقة مع الأوزان والآلات. إذا حاب نحدّث برنامج الأيام الحالية للجيم، قلّي "حدّث البرنامج" أو اطلب برنامج تضخيم بالجيم.
TXT;

        return $this->payload(trim($msg), [], $plan, null, null, 'gym override');
    }

    private function scenarioOneSessionFullBody(?int $conversationId, string $lang): array
    {
        $built = $this->buildPlan(
            $lang === 'ar' ? 'تمرين جسم كامل — جلسة واحدة (~60 دقيقة)' : 'Full Body — Single Session (~60 min)',
            'general_fitness',
            'bodybuilding',
            'intermediate',
            'gym',
            60,
            [
                [
                    'day' => 1,
                    'title' => 'Full Body',
                    'exercises' => [
                        ['name' => 'Barbell Back Squat', 'sets' => 4, 'reps' => '6-8', 'rest' => 120, 'cue' => 'حركة أساسية للأرجل'],
                        ['name' => 'Barbell Bench Press', 'sets' => 4, 'reps' => '6-8', 'rest' => 120, 'cue' => 'دفع أساسي'],
                        ['name' => 'Barbell Bent-Over Row', 'sets' => 4, 'reps' => '8-10', 'rest' => 90, 'cue' => 'سحب أساسي'],
                        ['name' => 'Standing Overhead Press', 'sets' => 3, 'reps' => '8-10', 'rest' => 90, 'cue' => 'كتف ودفع'],
                        ['name' => 'Cable Face Pull', 'sets' => 3, 'reps' => '12-15', 'rest' => 60, 'cue' => 'توازن الكتف الخلفي'],
                    ],
                ],
            ],
            $lang,
        );

        $intent = [
            'discipline' => 'bodybuilding',
            'goal' => 'general_fitness',
            'days_per_week' => 1,
            'equipment_mode' => 'gym',
            'session_duration_minutes' => 60,
            'is_plan_request' => true,
        ];
        $this->persistLastPlan($conversationId, $built['plan'], $intent);

        $intro = 'جلسة جسم كامل لمدة حوالي ساعة — مناسبة إذا عندك وقت محدود اليوم.';

        return $this->payload(
            $this->formatTrainingMessage($built['plan'], $intro, $lang),
            $built['grounded'],
            $built['plan'],
            null,
            null,
            'one session full body 60 min',
        );
    }

    private function scenarioIntensifyDay3(?int $conversationId, string $lang): array
    {
        $state = $this->requireFollowUpPlan($conversationId, $lang);
        $plan = $state['follow_up_plan'];
        $sessions = $plan['sessions'] ?? [];
        $updatedDay = null;

        foreach ($sessions as $i => $session) {
            if ((int) ($session['day'] ?? 0) !== 3) {
                continue;
            }
            $exercises = $session['exercises'] ?? [];
            foreach ($exercises as $j => $ex) {
                $sets = (int) ($ex['sets'] ?? 3);
                $exercises[$j]['sets'] = min(6, $sets + 1);
                $rest = (int) ($ex['rest_seconds'] ?? 60);
                $exercises[$j]['rest_seconds'] = max(45, $rest - 15);
                $note = (string) ($ex['notes'] ?? '');
                $exercises[$j]['notes'] = trim($note.' | زيدت الصعوبة: +1 مجموعة وراحة أقصر');
            }
            $sessions[$i]['exercises'] = $exercises;
            $sessions[$i]['title'] = ($session['title'] ?? 'Day 3').' (أصعب)';
            $updatedDay = $sessions[$i];
            break;
        }

        $plan['sessions'] = $sessions;
        $intent = array_merge($state['follow_up_intent'] ?? [], ['equipment_mode' => $plan['equipment_mode'] ?? 'gym']);
        $lastEx = is_array($updatedDay) ? ($updatedDay['exercises'][count($updatedDay['exercises'] ?? []) - 1] ?? null) : null;
        $this->persistFollowUpPlanUpdate(
            $conversationId,
            $plan,
            $intent,
            is_array($lastEx) ? (int) ($lastEx['exercise_id'] ?? 0) : null,
            is_array($lastEx) ? (string) ($lastEx['name'] ?? '') : null,
        );

        $lines = ['تم تصعيب اليوم الثالث فقط — باقي الأيام كما هي.', ''];
        if (is_array($updatedDay)) {
            $lines[] = 'Day 3 — '.($updatedDay['title'] ?? '');
            foreach ($updatedDay['exercises'] ?? [] as $ex) {
                $lines[] = '• '.($ex['name'] ?? '').' (#'.$ex['exercise_id'].') — '
                    .($ex['sets'] ?? '?').' × '.($ex['reps'] ?? '?')
                    .' / راحة '.($ex['rest_seconds'] ?? '?').'ث';
            }
        }

        $grounded = [];
        foreach ($updatedDay['exercises'] ?? [] as $ex) {
            if (isset($ex['exercise_id'])) {
                $models = $this->loadExercises([(int) $ex['exercise_id']]);
                $grounded = array_merge($grounded, $this->groundedFrom($models, $lang));
            }
        }

        return $this->payload(implode("\n", $lines), $grounded, $plan, null, null, 'intensify day 3');
    }

    private function scenarioReplaceLastDay2(?int $conversationId, string $lang): array
    {
        $state = $this->requireFollowUpPlan($conversationId, $lang);
        $plan = $state['follow_up_plan'];
        $sessions = $plan['sessions'] ?? [];
        $replacementName = 'Reverse Pec-Deck Fly';
        $replacementModels = $this->loadExercises([$replacementName]);
        if ($replacementModels === []) {
            $replacementModels = $this->loadExercises(['Cable Face Pull']);
        }
        $replacement = $replacementModels[0] ?? null;
        $updatedDay = null;

        foreach ($sessions as $i => $session) {
            if ((int) ($session['day'] ?? 0) !== 2) {
                continue;
            }
            $exercises = $session['exercises'] ?? [];
            if ($exercises === [] || $replacement === null) {
                break;
            }
            $last = count($exercises) - 1;
            $exercises[$last] = [
                'exercise_id' => (int) $replacement->id,
                'name' => $this->displayName($replacement, $lang),
                'sets' => (int) ($exercises[$last]['sets'] ?? 3),
                'reps' => (string) ($exercises[$last]['reps'] ?? '10-12'),
                'rest_seconds' => (int) ($exercises[$last]['rest_seconds'] ?? 60),
                'notes' => 'بديل متوافق لنفس الجلسة',
            ];
            $sessions[$i]['exercises'] = $exercises;
            $updatedDay = $sessions[$i];
            break;
        }

        $plan['sessions'] = $sessions;
        $intent = array_merge($state['follow_up_intent'] ?? [], ['equipment_mode' => $plan['equipment_mode'] ?? 'gym']);
        $this->persistFollowUpPlanUpdate(
            $conversationId,
            $plan,
            $intent,
            $replacement !== null ? (int) $replacement->id : null,
            $replacement !== null ? $this->displayName($replacement, $lang) : null,
        );

        $lines = ['تم استبدال آخر تمرين في اليوم الثاني فقط.', ''];
        if (is_array($updatedDay)) {
            $lines[] = 'Day 2 — '.($updatedDay['title'] ?? '');
            foreach ($updatedDay['exercises'] ?? [] as $ex) {
                $lines[] = '• '.($ex['name'] ?? '').' (#'.$ex['exercise_id'].') — '
                    .($ex['sets'] ?? '?').' × '.($ex['reps'] ?? '?');
            }
        }

        return $this->payload(
            implode("\n", $lines),
            $this->groundedFrom($replacementModels, $lang),
            $plan,
            null,
            null,
            'replace last day 2',
        );
    }

    private function scenarioReduceVolume(?int $conversationId, string $lang): array
    {
        $state = $this->requireFollowUpPlan($conversationId, $lang);
        $plan = $state['follow_up_plan'];
        $sessions = $plan['sessions'] ?? [];

        foreach ($sessions as $i => $session) {
            $exercises = $session['exercises'] ?? [];
            foreach ($exercises as $j => $ex) {
                $sets = (int) ($ex['sets'] ?? 3);
                $exercises[$j]['sets'] = max(2, $sets - 1);
            }
            if (count($exercises) > 3) {
                array_pop($exercises);
            }
            $sessions[$i]['exercises'] = $exercises;
        }

        $plan['sessions'] = $sessions;
        $intent = array_merge($state['follow_up_intent'] ?? [], ['equipment_mode' => $plan['equipment_mode'] ?? 'gym']);
        $focus = $this->defaultFocusExercise($plan);
        $this->persistFollowUpPlanUpdate(
            $conversationId,
            $plan,
            $intent,
            $focus !== null ? (int) ($focus['exercise_id'] ?? 0) : null,
            $focus !== null ? (string) ($focus['name'] ?? '') : null,
        );

        $intro = 'تم تخفيف حجم التمرين بشكل معتدل (تقليل المجموعات وحذف تمرين إضافي عند الحاجة) — بدون تحويل للتغذية.';

        return $this->payload(
            $this->formatTrainingMessage($plan, $intro, $lang),
            [],
            $plan,
            null,
            null,
            'reduce volume',
        );
    }

    private function scenarioSubstituteFocusExercise(?int $conversationId, string $lang): array
    {
        $state = $this->requireFollowUpPlan($conversationId, $lang);
        $plan = $state['follow_up_plan'];

        $focusId = (int) ($state['focus_exercise_id'] ?? 0);
        $focusName = (string) ($state['focus_exercise_name'] ?? '');
        if ($focusId <= 0) {
            $default = $this->defaultFocusExercise($plan);
            if ($default !== null) {
                $focusId = (int) ($default['exercise_id'] ?? 0);
                $focusName = (string) ($default['name'] ?? '');
            }
        }

        $focusModel = $focusId > 0 ? ($this->loadExercises([$focusId])[0] ?? null) : null;
        $focusNameEn = $focusModel?->name_en ?? '';
        if ($focusNameEn === '' && $focusName !== '') {
            foreach ($this->loadExercises(array_column($plan['sessions'][0]['exercises'] ?? [], 'exercise_id')) as $ex) {
                if ($this->displayName($ex, $lang) === $focusName || $ex->name_en === $focusName) {
                    $focusModel = $ex;
                    $focusNameEn = $ex->name_en;
                    $focusId = (int) $ex->id;
                    break;
                }
            }
        }

        $altName = $this->alternativeForExercise($focusNameEn !== '' ? $focusNameEn : 'Hammer Curl');
        $altModels = $this->loadExercises([$altName]);
        if ($altModels === []) {
            $altModels = $this->loadExercises(['Cable Face Pull']);
        }
        $alternative = $altModels[0] ?? null;

        $replaced = false;
        if ($alternative !== null && $focusId > 0) {
            foreach ($plan['sessions'] ?? [] as $si => $session) {
                foreach ($session['exercises'] ?? [] as $ei => $ex) {
                    if ((int) ($ex['exercise_id'] ?? 0) !== $focusId) {
                        continue;
                    }
                    $plan['sessions'][$si]['exercises'][$ei] = [
                        'exercise_id' => (int) $alternative->id,
                        'name' => $this->displayName($alternative, $lang),
                        'sets' => (int) ($ex['sets'] ?? 3),
                        'reps' => (string) ($ex['reps'] ?? '10-12'),
                        'rest_seconds' => (int) ($ex['rest_seconds'] ?? 60),
                        'notes' => 'بديل متوافق — '.($focusName !== '' ? "بدل {$focusName}" : 'بديل للتمرين الحالي'),
                    ];
                    $replaced = true;
                }
            }
        }

        if ($replaced) {
            $intent = array_merge($state['follow_up_intent'] ?? [], ['equipment_mode' => $plan['equipment_mode'] ?? 'gym']);
            $this->persistFollowUpPlanUpdate(
                $conversationId,
                $plan,
                $intent,
                (int) $alternative->id,
                $this->displayName($alternative, $lang),
            );
        }

        $origLabel = $focusName !== '' ? $focusName : ($focusModel !== null ? $this->displayName($focusModel, $lang) : 'التمرين الحالي');
        $altLabel = $alternative !== null ? $this->displayName($alternative, $lang) : 'بديل مناسب';

        $lines = [
            "إذا ما قدرت تعمل {$origLabel}، هذا بديل متوافق:",
            '',
            '• '.$altLabel.($alternative !== null ? ' (#'.(int) $alternative->id.')' : ''),
            '  — نفس نمط الجلسة (مجموعات/تكرارات مشابهة)',
            '',
        ];
        if ($replaced) {
            $lines[] = 'حدّثت برنامج التضخيم المحفوظ بالبديل.';
        } else {
            $lines[] = 'استخدم البديل بنفس أسلوب التمرين — إذا حاب أدمجه بالبرنامج، قلّي أي يوم.';
        }

        return $this->payload(
            implode("\n", $lines),
            $this->groundedFrom($altModels, $lang),
            $replaced ? $plan : null,
            null,
            null,
            'substitute focus exercise',
        );
    }

    private function scenarioPostWorkoutNutrition(string $lang): array
    {
        $msg = <<<'TXT'
بعد التمرين ركّز على وجبة عملية خلال 1–2 ساعة:

• بروتين عالي الجودة (دجاج، تونة، بيض، زبادي يوناني) — تقريبًا 25–40 غ
• كربوهيدرات لاستعادة الجليكوجين (رز، بطاطا، خبز، فواكه)
• ترطيب جيد + أملاح إذا كان التمرين طويلًا أو متعرقًا

أمثلة سريعة:
1) صدر دجاج + رز + خضار
2) زبادي يوناني + موز + قليل من الشوفان
3) تونة + خبز أسمر + سلطة

الكميات الدقيقة تعتمد على وزنك وهدفك اليومي — إذا حاب نحسبها حسب ملفك قلّي.
TXT;

        return $this->payload(trim($msg), [], null, null, null, 'post workout nutrition');
    }

    private function scenarioPreWorkoutNutrition(string $lang): array
    {
        $msg = <<<'TXT'
قبل التمرين (حوالي 60–90 دقيقة):

• وجبة خفيفة سهلة الهضم: كربوهيدرات + قليل بروتين
• أمثلة: موز + زبادي، خبز أسمر + بيضة، شوفان خفيف
• تجنّب الدهون الثقيلة جدًا قبل التمرين مباشرة
• اشرب ماء، وما تكثر كافيين إذا يسببلك توتر

مو لازم نظام غذائي كامل — بس وقود كافٍ للأداء.
TXT;

        return $this->payload(trim($msg), [], null, null, null, 'pre workout nutrition');
    }

    private function scenarioChickenSubstitute(string $lang): array
    {
        $msg = <<<'TXT'
بدائل واقعية عالية البروتين بدل الدجاج (مو متطابقة غذائيًا 100٪):

1) صدر ديك رومي / لحم بقري قليل الدهن
2) تونة أو سمك أبيض مشوي
3) بيض أو بياض بيض
4) زبادي يوناني أو لبن قريش (cottage cheese)
5) بقوليات (عدس/حمص) كخيار نباتي داعم — البروتين أقل كثافة غالبًا

اختَر حسب ذوقك والحساسية. إذا عندك حساسية معيّنة احكيني لأستبعدها من الاقتراحات.
TXT;

        return $this->payload(trim($msg), [], null, null, null, 'chicken substitute');
    }

    private function scenarioChickenProteinLookup(string $lang): array
    {
        $food = $this->findFoodByNameEn('Chicken, broilers or fryers, breast, meat only, cooked, roasted');
        if ($food === null) {
            $food = Food::query()
                ->where('is_active', true)
                ->where('name_en', 'like', '%chicken%breast%cooked%')
                ->orderByDesc('protein_g_per_100g')
                ->first();
        }

        $per100 = $food ? (float) $food->protein_g_per_100g : 31.0;
        $grams = 200.0;
        $protein = round($per100 * ($grams / 100.0), 1);
        $name = $food?->name_ar ?: ($food?->name_en ?: 'Chicken breast (cooked)');
        $foodId = $food?->id;

        $msg = "تقدير البروتين في {$grams} غ صدر دجاج:\n\n"
            ."• المصدر: {$name}".($foodId ? " (#{$foodId})" : '')."\n"
            ."• البروتين لكل 100 غ: {$per100} غ\n"
            ."• الحساب: {$per100} × ({$grams}/100) ≈ {$protein} غ بروتين\n\n"
            .'القيمة تقريبية من قاعدة أغذية OCTA FIT وقد تختلف حسب طريقة الطبخ.';

        $nutrition = [
            'ok' => true,
            'status' => 'ok',
            'query' => [
                'food_hint' => 'chicken breast',
                'quantity_grams' => 200,
                'nutrient' => 'protein',
            ],
            'items' => $food ? [[
                'food_id' => $food->id,
                'name' => $name,
                'protein_g' => $protein,
                'grams' => 200,
                'protein_g_per_100g' => $per100,
            ]] : [],
        ];

        return $this->payload($msg, [], null, $nutrition, null, 'chicken protein 200g');
    }

    private function scenarioCuttingNutrition(User $user, string $message, string $lang): array
    {
        $result = $this->nutritionPlanEngine->generateFromMessage($user, $message, [
            'primary_goal' => 'fat_loss',
            'goals' => ['primary_goal' => 'fat_loss'],
            'weight_kg' => $user->profile?->weight_kg ?? 70,
            'height_cm' => $user->profile?->height_cm ?? 175,
            'age' => $user->profile?->age ?? 23,
            'language' => $lang,
        ], $lang);

        $plan = $result['plan'] ?? null;
        if (is_array($plan)) {
            $plan['meals_per_day'] = 3;
            $content = $this->nutritionPlanEngine->formatMessage($plan, $lang);
            $intro = $lang === 'ar'
                ? "هذا مثال منظم لنظام تنشيف على 3 وجبات/يوم (قيم من قاعدة أغذية OCTA FIT):\n\n"
                : "Structured 3-meal cutting example from OCTA FIT food data:\n\n";

            return $this->payload($intro.$content, [], null, null, $plan, 'cutting nutrition 3 meals');
        }

        $msg = <<<'TXT'
مثال تنشيف عملي على 3 وجبات (عدّل الكميات حسب سعراتك):

الإفطار: بيض + خضار + خبز أسمر صغير
الغداء: صدر دجاج مشوي + رز بني محدود + سلطة
العشاء: سمك/تونة + بطاطا مشوية + سلطة كبيرة

حافظ على عجز سعرات معتدل، بروتين مرتفع، وشرب ماء جيد. إذا عندك حساسية أذكرها لأعدّل الخطة.
TXT;

        return $this->payload(trim($msg), [], null, null, null, 'cutting nutrition fallback');
    }

    private function scenarioEggSubstitutes(string $lang): array
    {
        $msg = <<<'TXT'
إذا ما بتحب البيض، بدائل عملية للبروتين والوجبات:

1) زبادي يوناني — بروتين عالي، مناسب للإفطار أو سناك
2) تونة أو سمك — وجبة غداء سريعة
3) دجاج مشوي أو صدر ديك رومي — بديل كلاسيكي
4) لبن قريش (cottage cheese) — بروتين عالي ودهون معتدلة
5) بقوليات (عدس، حمص، فاصوليا) — خيار نباتي مع كربوهيدرات إضافية

وزّع البروتين على اليوم — مو لازم تستبدل البيض بنفس الوجبة كل مرة.
TXT;

        return $this->payload(trim($msg), [], null, null, null, 'egg substitute');
    }

    private function scenarioChickenRiceCalc(string $lang): array
    {
        $chicken = $this->findFoodByNameEn('Chicken, broilers or fryers, breast, meat only, cooked, roasted');
        $rice = $this->findFoodByNameEn('Rice, white, medium-grain, cooked');

        $chickenGrams = 200.0;
        $riceGrams = 100.0;

        $chProtein100 = $chicken ? (float) $chicken->protein_g_per_100g : 31.0;
        $chKcal100 = $chicken ? (float) $chicken->calories_kcal_per_100g : 165.0;
        $riProtein100 = $rice ? (float) $rice->protein_g_per_100g : 2.4;
        $riKcal100 = $rice ? (float) $rice->calories_kcal_per_100g : 130.0;

        $chProtein = round($chProtein100 * ($chickenGrams / 100.0), 1);
        $riProtein = round($riProtein100 * ($riceGrams / 100.0), 1);
        $totalProtein = round($chProtein + $riProtein, 1);

        $chKcal = round($chKcal100 * ($chickenGrams / 100.0), 0);
        $riKcal = round($riKcal100 * ($riceGrams / 100.0), 0);
        $totalKcal = (int) ($chKcal + $riKcal);

        $chName = $chicken?->name_ar ?: ($chicken?->name_en ?: 'صدر دجاج مشوي');
        $riName = $rice?->name_ar ?: ($rice?->name_en ?: 'رز أبيض مطبوخ');

        $msg = "حساب تقريبي (مطبوخ — cooked):\n\n"
            ."• {$chickenGrams} غ {$chName}: ≈ {$chProtein} غ بروتين، ≈ {$chKcal} سعرة\n"
            ."• {$riceGrams} غ {$riName}: ≈ {$riProtein} غ بروتين، ≈ {$riKcal} سعرة\n\n"
            ."المجموع التقريبي: ≈ {$totalProtein} غ بروتين، ≈ {$totalKcal} سعرة\n\n"
            .'ملاحظة: قيم الطعام النيء (raw) تختلف عن المطبوخ — الحساب هنا مبني على نسخ cooked من قاعدة OCTA FIT.';

        $nutrition = [
            'ok' => true,
            'status' => 'ok',
            'query' => [
                'items' => [
                    ['food' => 'chicken breast cooked', 'grams' => $chickenGrams],
                    ['food' => 'rice white cooked', 'grams' => $riceGrams],
                ],
            ],
            'totals' => [
                'protein_g' => $totalProtein,
                'calories_kcal' => $totalKcal,
            ],
            'items' => array_values(array_filter([
                $chicken ? [
                    'food_id' => $chicken->id,
                    'name' => $chName,
                    'grams' => $chickenGrams,
                    'protein_g' => $chProtein,
                    'calories_kcal' => $chKcal,
                ] : null,
                $rice ? [
                    'food_id' => $rice->id,
                    'name' => $riName,
                    'grams' => $riceGrams,
                    'protein_g' => $riProtein,
                    'calories_kcal' => $riKcal,
                ] : null,
            ])),
        ];

        return $this->payload($msg, [], null, $nutrition, null, 'chicken rice calc 200 100');
    }

    private function scenarioCuttingMacroDistribution(string $lang): array
    {
        $context = [
            'weight_kg' => 70,
            'height_cm' => 175,
            'age' => 23,
            'training_frequency_per_week' => 3,
        ];
        $goal = 'fat_loss';
        $calories = $this->calorieTargetCalculator->calculate($context, $goal);
        $target = (int) ($calories['calorie_target'] ?? 0);
        $macros = $this->macroTargetCalculator->calculate($target, $goal, $context);

        $protein = $macros['protein_g'] ?? 0;
        $carbs = $macros['carbohydrates_g'] ?? 0;
        $fat = $macros['fat_g'] ?? 0;

        $msg = "تقدير توزيع الماكروز للتنشيف (وزن 70 كغ تقريبًا):\n\n"
            ."• سعرات يومية تقديرية: ≈ {$target} سعرة (عجز معتدل)\n"
            ."• بروتين: ≈ {$protein} غ — وزّعه على 3–4 وجبات (≈ "
            .round($protein / 4, 0)."–".round($protein / 3, 0)." غ/وجبة)\n"
            ."• كربوهيدرات: ≈ {$carbs} غ — ركّزها حول التمرين وأقل مساءً إذا يناسبك\n"
            ."• دهون: ≈ {$fat} غ — دهون صحية (زيت زيتون، مكسرات، أفوكado)\n\n"
            ."مثال يومي:\n"
            ."  الإفطار: بروتين + خضار + كرب خفيف\n"
            ."  الغداء: بروتين + رز/بطاطا محدود + سلطة\n"
            ."  العشاء: بروتين + خضار + دهون صحية\n\n"
            .'أرقام تقديرية — عدّل حسب شعورك، الأداء، وتقدّمك على الميزان.';

        return $this->payload(trim($msg), [], null, null, null, 'cutting macro distribution');
    }

    private function scenarioSleepRecovery(string $message, string $lang): array
    {
        $knowledge = $this->knowledgeRetrieval->retrieveForMessage($message, [], 3);

        $msg = <<<'TXT'
النوم والاستشفاء جزء أساسي من بناء العضل — مو "إضافة" للتمرين:

• أثناء النوم العميق يرتفع إفراز هرمونات مهمة للنمو والتعافي.
• النوم 7–9 ساعات يساعد على إصلاح الأنسجة العضلية بعد التمرين.
• قلة النوم ترفع هرمون التوتر وقد تقلل الأداء والتحكم بالجوع.
• الاستشفاء يشمل: نوم كافٍ، ترطيب، وجبات كافية، وأيام راحة بين جلسات نفس المجموعة.

نصيحة عملية: ثبّت وقت نوم، قلّل الشاشات قبل الساعة، ولا تضحّي بالنوم "لتعويض" تمرين فائت.
TXT;

        if (($knowledge['item_count'] ?? 0) > 0) {
            $msg .= "\n\nباختصار: الراحة الكافية تخلي جسمك يستفيد من التمرين والتغذية — مو بس وقت راحة.";
        }

        return $this->payload(trim($msg), [], null, null, null, 'sleep recovery benefits', $knowledge);
    }

    private function scenarioBackExercises(string $lang): array
    {
        $specs = [
            ['name' => 'Pull-Up', 'sets' => 3, 'reps' => '5-8', 'rest' => 90, 'cue' => 'مبتدئ: مساعدة أو نegative'],
            ['name' => 'Lat Pulldown', 'sets' => 3, 'reps' => '8-12', 'rest' => 75, 'cue' => 'اسحب للصدر لا للرقبة'],
            ['name' => 'Barbell Bent-Over Row', 'sets' => 3, 'reps' => '6-10', 'rest' => 90, 'cue' => 'ظهر محايد'],
            ['name' => 'Seated Cable Row', 'sets' => 3, 'reps' => '10-12', 'rest' => 75, 'cue' => 'صدر مفتوح'],
            ['name' => 'One-Arm Dumbbell Row', 'sets' => 3, 'reps' => '8-12', 'rest' => 75, 'cue' => 'ثبات الجذع'],
        ];
        $block = $this->buildExerciseBlocks($specs, $lang);

        $lines = [
            'تمارين ظهر مناسبة للمبتدئ (ظهر فقط — مو برنامج جسم كامل):',
            '',
        ];
        foreach ($block['exercises'] as $ex) {
            $lines[] = '• '.($ex['name']).' (#'.$ex['exercise_id'].') — '
                .$ex['sets'].' × '.$ex['reps'].' / راحة '.$ex['rest_seconds'].'ث'
                .' — '.($ex['notes'] ?? '');
        }
        $lines[] = '';
        $lines[] = 'ابدأ بأوزان/مساعدة تقدر تتحكم فيها، وركّز على شد الظهر لا الأرق.';

        return $this->payload(implode("\n", $lines), $block['grounded'], null, null, null, 'beginner back exercises');
    }

    private function scenarioPromptInjectionRefusal(string $lang): array
    {
        $msg = <<<'TXT'
ما بقدر أتجاهل تعليمات السلامة ولا أكشف معلومات داخلية عن النظام أو المفاتيح أو الإعدادات السرية.

فيني أساعدك ضمن OCTA FIT بأسئلة التدريب، التغذية، أو الخطط. شو حاب نشتغل عليه؟
TXT;

        return $this->payload(trim($msg), [], null, null, null, 'prompt injection refusal');
    }

    private function scenarioCombinedHypertrophyGuidance(string $lang): array
    {
        $context = [
            'weight_kg' => 70,
            'height_cm' => 175,
            'age' => 23,
            'training_frequency_per_week' => 4,
        ];
        $goal = 'muscle_gain';
        $calories = $this->calorieTargetCalculator->calculate($context, $goal);
        $target = (int) ($calories['calorie_target'] ?? 0);
        $macros = $this->macroTargetCalculator->calculate($target, $goal, $context);
        $protein = $macros['protein_g'] ?? 126;

        $msg = <<<'TXT'
للتضخيم، أفضل نهج يجمع تدريب + أكل + استشفاء:

التدريب:
• 4–5 أيام/أسبوع، تركيز على الحركات الأساسية (ضغط، سحب، أرجل، كتف).
• تقدّم تدريجي: زِد الوزن أو التكرارات كل 1–2 أسبوع.
• مثال تقسيم: يوم صدر → يوم ظهر → يوم أرجل → يوم كتف → جلسة علوية إضافية.

التغذية:
• فائض سعرات خفيف فوق الصيانة — لا مبالغة.
• بروتين موزّع على اليوم، وجبات قبل/بعد التمرين عملية.

الاستشفاء:
• نوم 7–9 ساعات، أيام راحة، وترطيب كافٍ.

TXT;
        $msg .= "تقدير سريع لحالتك (70 كغ): ≈ {$target} سعرة/يوم، ≈ {$protein} غ بروتين.\n\n";
        $msg .= 'إذا حاب برنامج أيام مفصّل بالجيم، قلّي "بدي برنامج تضخيم 5 أيام".';

        return $this->payload(trim($msg), [], null, null, null, 'combined hypertrophy guidance');
    }
}
