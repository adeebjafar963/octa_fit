<?php

namespace App\Services\Physio\Demo;

/**
 * Expands demo exercise names into full structured prescription cards.
 * Instruction banks only — no invented media/exercise database IDs.
 */
final class PhysioDemoExerciseLibrary
{
    /**
     * @param  list<array<string, mixed>>  $exercises
     * @return list<array<string, mixed>>
     */
    public function enrich(array $exercises, string $language = 'en'): array
    {
        $out = [];
        foreach ($exercises as $index => $exercise) {
            if (! is_array($exercise)) {
                continue;
            }
            $out[] = $this->enrichOne($exercise, $index + 1, $language);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $exercise
     * @return array<string, mixed>
     */
    private function enrichOne(array $exercise, int $ordinal, string $language): array
    {
        $nameEn = trim((string) ($exercise['name'] ?? 'Exercise'));
        $bank = $this->bank()[$this->key($nameEn)] ?? null;

        $isAr = $language === 'ar';
        $name = $isAr && $bank !== null ? ($bank['name_ar'] ?? $nameEn) : ($bank['name_en'] ?? $nameEn);
        $purpose = $isAr && isset($bank['purpose_ar'])
            ? $bank['purpose_ar']
            : (string) ($exercise['purpose'] ?? ($bank['purpose_en'] ?? ''));
        $difficulty = $isAr
            ? ($bank['difficulty_ar'] ?? 'مبتدئ')
            : ($bank['difficulty_en'] ?? 'Beginner');
        $instructions = $isAr
            ? ($bank['instructions_ar'] ?? $this->defaultInstructionsAr($name))
            : ($bank['instructions_en'] ?? $this->defaultInstructionsEn($nameEn));
        $safety = $isAr
            ? ($bank['safety_ar'] ?? 'توقف إذا ظهر ألم حاد أو أعراض عصبية جديدة.')
            : ($bank['safety_en'] ?? ($exercise['caution'] ?? 'Stop if sharp pain or new neurological symptoms appear.'));

        $repsOrDuration = (string) ($exercise['reps_or_duration'] ?? '');
        [$reps, $duration] = $this->splitDose($repsOrDuration);

        return [
            'id' => 'demo-ex-'.$ordinal.'-'.$this->key($nameEn),
            'name' => $name,
            'name_en' => $nameEn,
            'purpose' => $purpose,
            'difficulty' => $difficulty,
            'instructions' => array_values($instructions),
            'sets' => (string) ($exercise['sets'] ?? ($bank['sets'] ?? '2')),
            'reps' => $reps,
            'duration' => $duration,
            'reps_or_duration' => $repsOrDuration !== '' ? $repsOrDuration : ($reps ?: $duration),
            'rest' => (string) ($exercise['rest'] ?? ($isAr ? '30–60 ثانية بين المجموعات' : '30–60 seconds between sets')),
            'frequency' => $isAr
                ? ($bank['frequency_ar'] ?? 'مرة واحدة في اليوم أو حسب التحمل')
                : ($bank['frequency_en'] ?? 'Once daily or as tolerated'),
            'intensity' => (string) ($exercise['intensity'] ?? ($isAr ? 'ضمن مدى مريح بلا ألم حاد' : 'Comfortable, pain-free range')),
            'progression' => (string) ($exercise['progression'] ?? ($isAr ? 'زد التكرار تدريجياً عند استقرار الأعراض' : 'Increase volume gradually as symptoms settle')),
            'safety_note' => $safety,
            'caution' => $safety,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitDose(string $dose): array
    {
        $dose = trim($dose);
        if ($dose === '') {
            return [null, null];
        }
        if (preg_match('/second|minute|ثانية|دقيقة|hold|holds/i', $dose) === 1) {
            return [null, $dose];
        }
        if (preg_match('/rep|تكرار/i', $dose) === 1) {
            return [$dose, null];
        }

        return [$dose, null];
    }

    private function key(string $name): string
    {
        $key = mb_strtolower(trim($name));
        $key = preg_replace('/[^a-z0-9]+/i', '-', $key) ?? $key;

        return trim($key, '-');
    }

    /**
     * @return list<string>
     */
    private function defaultInstructionsEn(string $name): array
    {
        return [
            'Set up in a comfortable, controlled position.',
            'Perform '.$name.' slowly with good form.',
            'Stay within a comfortable symptom range.',
            'Stop if symptoms sharply increase.',
        ];
    }

    /**
     * @return list<string>
     */
    private function defaultInstructionsAr(string $name): array
    {
        return [
            'اتخذ وضعية مريحة ومستقرة.',
            'نفّذ تمرين '.$name.' ببطء وبتحكم.',
            'ابقَ ضمن مدى أعراض مقبول.',
            'توقف فوراً إذا ازداد الألم بشكل حاد.',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function bank(): array
    {
        return [
            'chin-tucks' => [
                'name_en' => 'Chin Tucks',
                'name_ar' => 'سحب الذقن للخلف',
                'purpose_en' => 'Improve deep neck flexor control and reduce forward-head strain',
                'purpose_ar' => 'تحسين التحكم بعضلات الرقبة العميقة وتقليل الحمل الناتج عن الرأس للأمام',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Sit or stand tall with shoulders relaxed.',
                    'Gently draw the chin straight back (as if making a double chin).',
                    'Keep the eyes level; do not tilt the head up or down.',
                    'Hold briefly, then return to start without forcing range.',
                ],
                'instructions_ar' => [
                    'اجلس أو قف باستقامة مع استرخاء الكتفين.',
                    'اسحب الذقن للخلف بلطف (كأنك تصنع ذقناً مزدوجاً خفيفاً).',
                    'حافظ على مستوى النظر؛ لا تمل الرأس لأعلى أو لأسفل.',
                    'حافظ على الوضع لحظة ثم عد للبداية دون إجبار المدى.',
                ],
                'frequency_en' => '2–3 times daily',
                'frequency_ar' => '2–3 مرات يومياً',
                'safety_en' => 'Should feel gentle. Stop if dizziness, arm symptoms, or sharp pain appear.',
                'safety_ar' => 'يجب أن يكون الإحساس لطيفاً. توقف عند دوار أو أعراض في الذراع أو ألم حاد.',
            ],
            'upper-trapezius-stretch' => [
                'name_en' => 'Upper Trapezius Stretch',
                'name_ar' => 'إطالة شبه المنحرفة العلوية',
                'purpose_en' => 'Reduce upper neck/shoulder muscle tension',
                'purpose_ar' => 'تقليل توتر عضلات الرقبة والكتف العلوي',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Sit tall and gently tilt one ear toward the same-side shoulder.',
                    'Use light hand assistance only if comfortable.',
                    'Keep the opposite shoulder down.',
                    'Breathe calmly and avoid bouncing.',
                ],
                'instructions_ar' => [
                    'اجلس باستقامة وأمل الأذن نحو الكتف في نفس الجهة برفق.',
                    'يمكن مساعدة خفيفة باليد إذا كانت مريحة.',
                    'حافظ على الكتف المقابل للأسفل.',
                    'تنفس بهدوء وتجنب الارتداد.',
                ],
                'safety_en' => 'Do not force into pain or numbness.',
                'safety_ar' => 'لا تجبر المدى إلى ألم أو تنميل.',
            ],
            'scapular-retraction' => [
                'name_en' => 'Scapular Retraction',
                'name_ar' => 'تقريب لوحي الكتف',
                'purpose_en' => 'Improve postural support for the neck and shoulder girdle',
                'purpose_ar' => 'تحسين الدعم الوضعي لحزام الكتف والرقبة',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Sit or stand tall.',
                    'Gently squeeze the shoulder blades toward each other.',
                    'Avoid shrugging the shoulders upward.',
                    'Hold briefly, then relax with control.',
                ],
                'instructions_ar' => [
                    'اجلس أو قف باستقامة.',
                    'قرّب لوحي الكتف بلطف نحو بعضهما.',
                    'تجنب رفع الكتفين للأعلى.',
                    'حافظ لحظة ثم أرخِ بتحكم.',
                ],
                'safety_en' => 'Keep the movement small and pain-free.',
                'safety_ar' => 'أبقِ الحركة صغيرة وخالية من الألم.',
            ],
            'deep-neck-flexor-activation' => [
                'name_en' => 'Deep Neck Flexor Activation',
                'name_ar' => 'تفعيل مثنيات الرقبة العميقة',
                'purpose_en' => 'Build gentle deep cervical stability',
                'purpose_ar' => 'بناء ثبات لطيف للرقبة العميقة',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Lie on your back with a small towel under the head if needed.',
                    'Perform a tiny nodding motion as if saying “yes”.',
                    'Keep superficial neck muscles relaxed.',
                    'Hold 5 seconds, then relax.',
                ],
                'instructions_ar' => [
                    'استلقِ على ظهرك مع منشفة صغيرة تحت الرأس عند الحاجة.',
                    'نفّذ إيماءة خفيفة جداً كأنك تقول «نعم».',
                    'حافظ على ارتخاء العضلات السطحية.',
                    'حافظ 5 ثوانٍ ثم أرخِ.',
                ],
                'safety_en' => 'Avoid strong pressing or breath holding.',
                'safety_ar' => 'تجنب الضغط القوي أو حبس النفس.',
            ],
            'walking' => [
                'name_en' => 'Walking',
                'name_ar' => 'المشي',
                'purpose_en' => 'Maintain general activity and circulation',
                'purpose_ar' => 'الحفاظ على النشاط العام وتحسين الدورة الدموية',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Choose comfortable footwear.',
                    'Walk at a pace that keeps symptoms acceptable.',
                    'Use shorter bouts if needed and build gradually.',
                    'Stop if symptoms rapidly escalate.',
                ],
                'instructions_ar' => [
                    'اختر حذاءً مريحاً.',
                    'امشِ بسرعة تبقي الأعراض ضمن مدى مقبول.',
                    'يمكن تقسيم المشي لفترات قصيرة ثم التدرج.',
                    'توقف إذا تصاعدت الأعراض بسرعة.',
                ],
                'frequency_en' => 'Daily as tolerated',
                'frequency_ar' => 'يومياً حسب التحمل',
                'safety_en' => 'Prefer comfortable movement over prolonged bed rest.',
                'safety_ar' => 'فضّل الحركة المريحة على الراحة المطلقة الطويلة.',
            ],
            'pelvic-tilt' => [
                'name_en' => 'Pelvic Tilt',
                'name_ar' => 'إمالة الحوض',
                'purpose_en' => 'Encourage gentle lumbar movement control',
                'purpose_ar' => 'تشجيع التحكم اللطيف بحركة أسفل الظهر',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Lie on your back with knees bent and feet flat.',
                    'Gently flatten the lower back into the floor by tilting the pelvis.',
                    'Avoid pressing forcefully.',
                    'Return to a neutral comfortable position.',
                ],
                'instructions_ar' => [
                    'استلقِ على ظهرك مع ثني الركبتين والقدمان على الأرض.',
                    'سطّح أسفل الظهر بلطف عبر إمالة الحوض.',
                    'تجنب الضغط بقوة.',
                    'عد إلى وضع محايد مريح.',
                ],
                'safety_en' => 'Stay within a comfortable range.',
                'safety_ar' => 'التزم بالمدى المريح فقط.',
            ],
            'bird-dog' => [
                'name_en' => 'Bird Dog',
                'name_ar' => 'تمرين الطائر-الكلب',
                'purpose_en' => 'Build trunk endurance and movement control',
                'purpose_ar' => 'بناء تحمل الجذع والتحكم الحركي',
                'difficulty_en' => 'Beginner–Intermediate',
                'difficulty_ar' => 'مبتدئ–متوسط',
                'instructions_en' => [
                    'Start on hands and knees with a neutral spine.',
                    'Reach one arm forward and the opposite leg backward.',
                    'Keep hips level and avoid twisting.',
                    'Return slowly and switch sides.',
                ],
                'instructions_ar' => [
                    'ابدأ على اليدين والركبتين مع عمود فقري محايد.',
                    'مدّ ذراعاً للأمام والساق المعاكسة للخلف.',
                    'حافظ على استواء الحوض وتجنب الالتواء.',
                    'عد ببطء وبدّل الجانب.',
                ],
                'safety_en' => 'Reduce range if low-back symptoms spike.',
                'safety_ar' => 'قلّل المدى إذا ازداد ألم أسفل الظهر.',
            ],
            'glute-bridge' => [
                'name_en' => 'Glute Bridge',
                'name_ar' => 'جسر الأرداف',
                'purpose_en' => 'Strengthen hip extensors and support the lumbar region',
                'purpose_ar' => 'تقوية باسطات الورك ودعم منطقة أسفل الظهر',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Lie on your back, knees bent, feet hip-width.',
                    'Press through the heels to lift the hips.',
                    'Squeeze the glutes at the top without overarching.',
                    'Lower with control.',
                ],
                'instructions_ar' => [
                    'استلقِ على ظهرك، الركبتان مثنيتان، القدمان بعرض الورك.',
                    'ادفع عبر الكعبين لرفع الحوض.',
                    'فعّل الأرداف في الأعلى دون تقوس مفرط.',
                    'انزل بتحكم.',
                ],
                'safety_en' => 'Avoid forcing through sharp lumbar pain.',
                'safety_ar' => 'تجنب الإجبار عند ألم قطني حاد.',
            ],
            'pendulum-exercise' => [
                'name_en' => 'Pendulum Exercise',
                'name_ar' => 'تمرين البندول',
                'purpose_en' => 'Promote gentle shoulder motion',
                'purpose_ar' => 'تشجيع حركة لطيفة لمفصل الكتف',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Lean forward and support the non-affected side on a chair.',
                    'Let the affected arm hang relaxed.',
                    'Make small circles or forward-back swings.',
                    'Keep the movement passive and comfortable.',
                ],
                'instructions_ar' => [
                    'انحنِ للأمام وادعم الجانب غير المصاب على كرسي.',
                    'اترك الذراع المصابة تتدلى باسترخاء.',
                    'ارسم دوائر صغيرة أو حركات أمام-خلف.',
                    'أبقِ الحركة سلبية ومريحة.',
                ],
                'safety_en' => 'Do not force large painful arcs.',
                'safety_ar' => 'لا تجبر أقواساً كبيرة مؤلمة.',
            ],
            'isometric-external-rotation' => [
                'name_en' => 'Isometric External Rotation',
                'name_ar' => 'تدوير خارجي ثابت',
                'purpose_en' => 'Activate rotator-cuff control without large motion',
                'purpose_ar' => 'تفعيل التحكم بعضلات الكفة المدورة دون حركة كبيرة',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Keep the elbow by your side bent to 90°.',
                    'Press the hand outward into a wall or immovable object.',
                    'Hold a gentle contraction without pain.',
                    'Relax and repeat.',
                ],
                'instructions_ar' => [
                    'أبقِ المرفق بجانب الجسم بزاوية 90°.',
                    'اضغط اليد للخارج على جدار أو جسم ثابت.',
                    'حافظ على تقلص لطيف بلا ألم.',
                    'أرخِ وكرر.',
                ],
                'safety_en' => 'Use low effort; stop if night pain sharply increases.',
                'safety_ar' => 'استخدم جهداً منخفضاً؛ توقف إذا ازداد ألم الليل بشكل حاد.',
            ],
            'quad-sets' => [
                'name_en' => 'Quad Sets',
                'name_ar' => 'تفعيل العضلة الرباعية',
                'purpose_en' => 'Restore quadriceps activation',
                'purpose_ar' => 'استعادة تفعيل العضلة الرباعية',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Sit or lie with the knee extended.',
                    'Tighten the front thigh to press the knee downward.',
                    'Hold 5 seconds.',
                    'Relax fully between reps.',
                ],
                'instructions_ar' => [
                    'اجلس أو استلقِ مع مد الركبة.',
                    'شدّ مقدمة الفخذ للضغط بالركبة للأسفل.',
                    'حافظ 5 ثوانٍ.',
                    'أرخِ تماماً بين التكرارات.',
                ],
                'safety_en' => 'Avoid if marked swelling and inability to bear weight after major injury.',
                'safety_ar' => 'تجنب التمرين إذا كان هناك تورم شديد مع عدم تحمل الوزن بعد إصابة كبيرة.',
            ],
            'straight-leg-raise' => [
                'name_en' => 'Straight Leg Raise',
                'name_ar' => 'رفع الساق المستقيمة',
                'purpose_en' => 'Build quadriceps strength with limited knee stress',
                'purpose_ar' => 'بناء قوة الرباعية مع ضغط محدود على الركبة',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Lie on your back with one knee bent and the other straight.',
                    'Tighten the thigh of the straight leg and lift it to the height of the bent knee.',
                    'Keep the knee straight.',
                    'Lower slowly.',
                ],
                'instructions_ar' => [
                    'استلقِ على ظهرك مع ثني ركبة وامتداد الأخرى.',
                    'شدّ فخذ الساق الممدودة وارفعها حتى مستوى الركبة المثنية.',
                    'حافظ على استقامة الركبة.',
                    'انزل ببطء.',
                ],
                'safety_en' => 'Skip if the knee locks, gives way severely, or cannot bear weight after trauma.',
                'safety_ar' => 'تجاوز التمرين إذا حدث قفل أو تخلخل شديد أو عدم تحمل وزن بعد رضّ.',
            ],
            'ankle-circles' => [
                'name_en' => 'Ankle Circles',
                'name_ar' => 'دوائر الكاحل',
                'purpose_en' => 'Restore gentle ankle mobility after sprain',
                'purpose_ar' => 'استعادة حركة الكاحل اللطيفة بعد الالتواء',
                'difficulty_en' => 'Beginner',
                'difficulty_ar' => 'مبتدئ',
                'instructions_en' => [
                    'Sit with the foot free of load.',
                    'Move the ankle in slow circles both directions.',
                    'Keep pain within a mild comfortable range.',
                    'Stop if swelling and pain rapidly worsen.',
                ],
                'instructions_ar' => [
                    'اجلس والقدم بلا حمل.',
                    'حرّك الكاحل بدوائر بطيئة بالاتجاهين.',
                    'أبقِ الألم ضمن مدى خفيف مقبول.',
                    'توقف إذا ازداد التورم والألم بسرعة.',
                ],
                'safety_en' => 'Not for suspected fracture or inability to bear weight.',
                'safety_ar' => 'غير مناسب عند الاشتباه بكسر أو عدم القدرة على تحمّل الوزن.',
            ],
            'single-leg-balance' => [
                'name_en' => 'Single-Leg Balance',
                'name_ar' => 'توازن على قدم واحدة',
                'purpose_en' => 'Rebuild proprioception and ankle stability',
                'purpose_ar' => 'إعادة بناء الإحساس العميق وثبات الكاحل',
                'difficulty_en' => 'Beginner–Intermediate',
                'difficulty_ar' => 'مبتدئ–متوسط',
                'instructions_en' => [
                    'Stand near support.',
                    'Lift one foot and keep balance with soft knee.',
                    'Hold for the prescribed time.',
                    'Progress only when stable and confident.',
                ],
                'instructions_ar' => [
                    'قف قرب دعامة.',
                    'ارفع قدماً وحافظ على التوازن مع ركبة مرتخية قليلاً.',
                    'حافظ للمدة المحددة.',
                    'تدرّج فقط عند الثبات والثقة.',
                ],
                'safety_en' => 'Use support; avoid if weight-bearing is unsafe.',
                'safety_ar' => 'استخدم دعماً؛ تجنب إذا كان تحمّل الوزن غير آمن.',
            ],
            'heel-raises' => [
                'name_en' => 'Heel Raises',
                'name_ar' => 'رفع الكعبين',
                'purpose_en' => 'Progressive calf and Achilles loading',
                'purpose_ar' => 'تحميل تدريجي لعضلة الساق ووتر أخيل',
                'difficulty_en' => 'Beginner–Intermediate',
                'difficulty_ar' => 'مبتدئ–متوسط',
                'instructions_en' => [
                    'Stand holding light support if needed.',
                    'Rise onto the balls of the feet.',
                    'Lower slowly with control.',
                    'Keep loading progressive, not sudden.',
                ],
                'instructions_ar' => [
                    'قف مع دعم خفيف عند الحاجة.',
                    'ارتفع على مقدمة القدمين.',
                    'انزل ببطء وتحكم.',
                    'اجعل التحميل تدريجياً وليس مفاجئاً.',
                ],
                'safety_en' => 'Avoid sprinting or jumping while highly irritable.',
                'safety_ar' => 'تجنب العدو أو القفز أثناء التهيج الشديد.',
            ],
            'isometric-quad-wall-sit-short' => [
                'name_en' => 'Short Wall Sit',
                'name_ar' => 'جلوس جداري قصير',
                'purpose_en' => 'Build knee capacity with controlled load',
                'purpose_ar' => 'بناء قدرة الركبة بتحميل مضبوط',
                'difficulty_en' => 'Intermediate',
                'difficulty_ar' => 'متوسط',
                'instructions_en' => [
                    'Stand with back against a wall.',
                    'Slide down to a shallow comfortable knee bend.',
                    'Hold without sharp pain.',
                    'Return by sliding up the wall.',
                ],
                'instructions_ar' => [
                    'قف مستنداً بظهرك إلى جدار.',
                    'انزل لثني ركبة ضحل ومريح.',
                    'حافظ دون ألم حاد.',
                    'عد بالانزلاق لأعلى.',
                ],
                'safety_en' => 'Avoid deep angles that sharply provoke tendon pain.',
                'safety_ar' => 'تجنب الزوايا العميقة التي تثير ألم الوتر بشدة.',
            ],
        ];
    }
}
