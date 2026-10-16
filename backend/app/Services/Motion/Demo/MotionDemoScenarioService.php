<?php

namespace App\Services\Motion\Demo;

/**
 * Video-specific educational motion-analysis profiles for the graduation demo.
 *
 * Source videos (inspected frame-by-frame):
 * - Squat: تسجيل 2026-10-05 203808.mp4 (bodyweight squat, side view, home)
 * - Deadlift: تسجيل 2026-10-05 203403.mp4 (dumbbell RDL / hinge, side view, gym)
 *
 * Active only when MOTION_DEMO_MODE is true.
 * Not a computer-vision measurement claim — findings are qualitative, video-based coaching notes.
 */
final class MotionDemoScenarioService
{
    public function enabled(): bool
    {
        return (bool) config('services.ai.motion_demo_mode', false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function tryBuild(string $exercise, string $language = 'en'): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $exercise = strtolower(trim($exercise));
        if (! in_array($exercise, ['squat', 'deadlift'], true)) {
            return null;
        }

        return $exercise === 'deadlift'
            ? $this->deadlift($language)
            : $this->squat($language);
    }

    /**
     * Profile derived from the supplied squat video (203808).
     * Visible: forward trunk lean, limited depth, loss of trunk control near bottom.
     * Not claimed: knee valgus (not clearly visible from this side angle), exact joint angles.
     *
     * @return array<string, mixed>
     */
    private function squat(string $language): array
    {
        $ar = $language === 'ar';

        return $this->payload(
            exercise: 'squat',
            language: $language,
            title: $ar ? 'القرفصاء' : 'Squat',
            analysisLabel: $ar ? 'تحليل الحركة' : 'Movement Analysis',
            heroSubtitle: $ar
                ? 'تحليل تعليمي مبني على حركة القرفصاء الظاهرة في الفيديو'
                : 'Educational analysis based on the squat movement visible in this video',
            overallStatus: $ar ? 'تحتاج إلى تحسين' : 'Needs improvement',
            overallSummary: $ar
                ? 'في هذا الفيديو تظهر قرفصاء بوزن الجسم من وضعية جانبية. وضعية البداية مستقرة والقدمان ثابتتان، لكن أثناء النزول يظهر ميل واضح للجذع للأمام، ولا يصل العمق إلى مستوى موازٍ تقريباً، مع فقدان نسبي لثبات الجذع قرب الجزء السفلي من الحركة.'
                : 'In this video a bodyweight squat is shown from a side view. The starting stance is stable and the feet stay planted, but during the descent there is a clear forward trunk lean, depth stays above a near-parallel position, and trunk control softens near the lower portion of the movement.',
            positivePoints: $ar ? [
                ['title' => 'وضعية البداية', 'explanation' => 'تبدأ الحركة من وقفة مستقرة مع توزيع مناسب لقاعدة الارتكاز.'],
                ['title' => 'ثبات القدمين', 'explanation' => 'تبقى القدمان على السطح والكعبان ملتصقين أثناء النزول والصعود.'],
                ['title' => 'تسلسل الحركة', 'explanation' => 'الحركة متسلسلة بشكل منظم من الوقوف إلى النزول ثم العودة.'],
                ['title' => 'استخدام الذراعين للتوازن', 'explanation' => 'اليدان أمام الصدر تساعدان على الحفاظ على التوازن أثناء الحركة.'],
            ] : [
                ['title' => 'Starting position', 'explanation' => 'The movement begins from a stable stance with a solid base of support.'],
                ['title' => 'Foot stability', 'explanation' => 'Both feet stay on the surface and the heels remain down through the descent and ascent.'],
                ['title' => 'Movement sequence', 'explanation' => 'The squat sequence from standing to descent and back up is organized.'],
                ['title' => 'Arm balance assist', 'explanation' => 'Hands held in front of the chest help maintain balance during the movement.'],
            ],
            detectedIssues: $ar ? [
                [
                    'title' => 'ميل الجذع للأمام',
                    'status' => 'attention',
                    'status_label' => 'يحتاج تركيزاً',
                    'explanation' => 'أثناء النزول يظهر ميل واضح للجذع نحو الأمام، فيصبح الصدر أكثر انخفاضاً بدل الحفاظ على جذع أكثر استقراراً وارتفاعاً نسبياً.',
                    'correction' => 'حاول النزول مع دفع الوركين للخلف قليلاً مع الحفاظ على الصدر مرفوعاً قدر الإمكان، وتخيل أنك تجلس على مقعد خلفك بدل الانحناء للأمام.',
                ],
                [
                    'title' => 'عمق الحركة',
                    'status' => 'improve',
                    'status_label' => 'يحتاج تحسيناً',
                    'explanation' => 'في أعمق نقطة ظاهرة في الفيديو تبقى الفخذان أعلى من وضعية الموازاة تقريباً، أي أن العمق محدود مقارنة بنمط قرفصاء مكتمل التحكم.',
                    'correction' => 'زد العمق تدريجياً فقط بالقدر الذي تستطيع فيه الحفاظ على ثبات القدمين والجذع، ويمكنك استخدام مقعد منخفض كهدف للوصول إليه بتحكم.',
                ],
                [
                    'title' => 'ثبات الجذع في الجزء السفلي',
                    'status' => 'improve',
                    'status_label' => 'يحتاج تحسيناً',
                    'explanation' => 'قرب الجزء السفلي من الحركة يظهر فقدان نسبي لثبات الجذع، مع ميل لفقدان استقامة الوضعية أثناء الانتقال من النزول إلى الصعود.',
                    'correction' => 'قبل النزول شد عضلات الجذع بلطف، وحافظ على هذا التثبيت خلال أعمق نقطة ثم اصعد دون فقدان الوضعية.',
                ],
            ] : [
                [
                    'title' => 'Forward trunk lean',
                    'status' => 'attention',
                    'status_label' => 'Needs focus',
                    'explanation' => 'During the descent the trunk leans clearly forward, with the chest dropping instead of staying relatively taller and more stable.',
                    'correction' => 'Sit the hips slightly back while keeping the chest up as much as possible — imagine sitting onto a bench behind you rather than folding forward.',
                ],
                [
                    'title' => 'Movement depth',
                    'status' => 'improve',
                    'status_label' => 'Needs improvement',
                    'explanation' => 'At the deepest visible point the thighs stay above a near-parallel position, so depth remains limited compared with a more complete controlled squat.',
                    'correction' => 'Increase depth gradually only while the feet and trunk stay stable. A low box can serve as a controlled depth target.',
                ],
                [
                    'title' => 'Trunk control near the bottom',
                    'status' => 'improve',
                    'status_label' => 'Needs improvement',
                    'explanation' => 'Near the lower portion of the movement, trunk stability softens and posture control dips during the transition from descent to ascent.',
                    'correction' => 'Brace the trunk lightly before descending, keep that brace through the deepest point, then stand up without losing posture.',
                ],
            ],
            coachingCues: $ar
                ? [
                    'أنزل الوركين للخلف وللأسفل بدل الميل الشديد للأمام.',
                    'حافظ على الصدر مرفوعاً قدر الإمكان أثناء النزول.',
                    'زد العمق تدريجياً مع بقاء الكعبين على الأرض.',
                    'ثبّت الجذع قبل الوصول إلى أعمق نقطة.',
                    'اصعد بنفس التحكم دون فقدان الوضعية.',
                ]
                : [
                    'Drop the hips back and down instead of folding hard forward.',
                    'Keep the chest as tall as possible during the descent.',
                    'Increase depth gradually while the heels stay down.',
                    'Brace the trunk before reaching the deepest point.',
                    'Stand up with the same control without losing posture.',
                ],
            commonMistakes: $ar ? [
                ['title' => 'الانحناء المفرط للأمام', 'explanation' => 'الميل الشديد للجذع يقلل جودة نمط القرفصاء ويجعل التحكم أصعب.'],
                ['title' => 'التوقف عند عمق محدود', 'explanation' => 'العمق المحدود يقلل الاستفادة من كامل نمط الحركة إذا كان يمكن الوصول أعمق بتحكم.'],
                ['title' => 'فقدان تثبيت الجذع في الأسفل', 'explanation' => 'ضعف التثبيت قرب أعمق نقطة يضعف جودة الانتقال إلى الصعود.'],
            ] : [
                ['title' => 'Excessive forward fold', 'explanation' => 'A heavy forward trunk lean reduces squat pattern quality and makes control harder.'],
                ['title' => 'Stopping at limited depth', 'explanation' => 'Limited depth reduces how fully the squat pattern is expressed when deeper control is available.'],
                ['title' => 'Losing trunk brace at the bottom', 'explanation' => 'Losing brace near the deepest point weakens the transition into the ascent.'],
            ],
            drills: $ar ? [
                $this->drill('Goblet Squat', 'قرفصاء الكأس', 'تشجيع جذع أكثر ارتفاعاً وتقليل الميل الأمامي.', 'مبتدئ–متوسط', '2–3', '8–10', '60–90 ثانية', ['امسك وزناً خفيفاً أمام الصدر إن توفر.', 'انزل بدفع الوركين للخلف مع بقاء الصدر مرفوعاً.', 'ركز على الوضعية لا على الحمل.']),
                $this->drill('Box Squat', 'قرفصاء الصندوق', 'تحسين العمق بشكل تدريجي ومتحكم.', 'مبتدئ', '2–3', '6–8', '60–90 ثانية', ['ضع مقعداً أو صندوقاً خلفك بارتفاع مناسب.', 'انزل حتى تلمسه برفق ثم اصعد.', 'زد صعوبة العمق تدريجياً مع الحفاظ على التحكم.']),
                $this->drill('Tempo Squat', 'قرفصاء بإيقاع بطيء', 'تحسين التحكم أثناء النزول وثبات الجذع.', 'مبتدئ–متوسط', '2–3', '6–8', '60–90 ثانية', ['انزل خلال حوالي 3 ثوانٍ.', 'حافظ على تثبيت الجذع والكعبين على الأرض.', 'اصعد بثبات دون اندفاع.']),
                $this->drill('Pause Squat', 'قرفصاء مع توقف', 'تعزيز الثبات في الجزء السفلي من الحركة.', 'متوسط', '2–3', '5–8', '90 ثانية', ['انزل إلى عمق مناسب يمكنك التحكم به.', 'توقف لحظة قصيرة مع بقاء الجذع ثابتاً.', 'اصعد مع الحفاظ على الوضعية.']),
            ] : [
                $this->drill('Goblet Squat', 'Goblet Squat', 'Encourage a taller trunk and reduce forward lean.', 'Beginner–Intermediate', '2–3', '8–10', '60–90 seconds', ['Hold a light load at the chest if available.', 'Sit the hips back while keeping the chest up.', 'Prioritize posture over load.']),
                $this->drill('Box Squat', 'Box Squat', 'Improve depth gradually with control.', 'Beginner', '2–3', '6–8', '60–90 seconds', ['Place a bench or box at a suitable height behind you.', 'Descend until you lightly touch it, then stand up.', 'Progress depth only while control stays solid.']),
                $this->drill('Tempo Squat', 'Tempo Squat', 'Improve descent control and trunk stability.', 'Beginner–Intermediate', '2–3', '6–8', '60–90 seconds', ['Descend for about 3 seconds.', 'Keep the trunk braced and heels down.', 'Stand up smoothly without rushing.']),
                $this->drill('Pause Squat', 'Pause Squat', 'Reinforce stability in the lower portion of the squat.', 'Intermediate', '2–3', '5–8', '90 seconds', ['Descend to a depth you can control.', 'Pause briefly while keeping the trunk stable.', 'Stand up without losing posture.']),
            ],
            nextAttempt: $ar
                ? [
                    'ابدأ من وقفة مستقرة كما فعلت.',
                    'ادفع الوركين للخلف وللأسفل بدل الميل الشديد للأمام.',
                    'حافظ على الصدر مرفوعاً أثناء النزول.',
                    'زد العمق بدرجة بسيطة مع بقاء الكعبين ثابتين.',
                    'ثبّت الجذع في الأسفل ثم اصعد بتحكم.',
                ]
                : [
                    'Start from the same stable stance.',
                    'Sit the hips back and down instead of folding hard forward.',
                    'Keep the chest tall during the descent.',
                    'Add a little more depth while the heels stay planted.',
                    'Brace at the bottom, then stand up with control.',
                ],
            summary: $ar
                ? 'الملاحظة الأبرز في هذا الفيديو هي الميل الأمامي للجذع مع عمق محدود وفقدان نسبي لثبات الجذع في الأسفل. ركّز في المحاولة القادمة على النزول للخلف وللأسفل مع صدر أكثر ارتفاعاً وعمق تدريجي متحكم.'
                : 'The clearest notes in this video are forward trunk lean, limited depth, and softer trunk control near the bottom. On the next attempt, sit back and down with a taller chest and a gradually deeper, controlled squat.',
        );
    }

    /**
     * Profile derived from the supplied deadlift video (203403).
     * Visible: spinal rounding at the bottom, head looking down, load drifting slightly away at times.
     * Positive: hip hinge present, soft knees, stable feet, arms straight.
     *
     * @return array<string, mixed>
     */
    private function deadlift(string $language): array
    {
        $ar = $language === 'ar';

        return $this->payload(
            exercise: 'deadlift',
            language: $language,
            title: $ar ? 'الرفعة المميتة' : 'Deadlift',
            analysisLabel: $ar ? 'تحليل الحركة' : 'Movement Analysis',
            heroSubtitle: $ar
                ? 'تحليل تعليمي مبني على حركة الرفعة المميتة الظاهرة في الفيديو'
                : 'Educational analysis based on the deadlift movement visible in this video',
            overallStatus: $ar ? 'تحتاج إلى تحسين' : 'Needs improvement',
            overallSummary: $ar
                ? 'في هذا الفيديو تظهر حركة رفع ميتة بنمط مفصلة الورك (شبه رومانية) باستخدام أوزان يدوية من وضعية جانبية. نمط المفصلة موجود والقدمان ثابتتان، لكن في الجزء السفلي يظهر تقوس واضح في الظهر مع النظر للأسفل، وأحياناً يبتعد الحمل قليلاً عن الجسم.'
                : 'In this video a hip-hinge deadlift pattern (Romanian-style) with hand weights is shown from a side view. The hinge pattern is present and the feet are stable, but clear back rounding appears in the lower portion of the movement with the gaze directed down, and the load occasionally drifts slightly away from the body.',
            positivePoints: $ar ? [
                ['title' => 'نمط مفصلة الورك', 'explanation' => 'يظهر دفع واضح للوركين للخلف، وهو أساس حركة الرفعة المميتة.'],
                ['title' => 'انحناء خفيف في الركبتين', 'explanation' => 'الركبتان تبقيان بليونة مناسبة دون تحويل الحركة إلى قرفصاء.'],
                ['title' => 'ثبات القدمين', 'explanation' => 'قاعدة الارتكاز تبقى ثابتة على الأرض طوال الحركة الظاهرة.'],
                ['title' => 'استقامة الذراعين', 'explanation' => 'الذراعان تبقيان ممدودتين وتحملان الوزن دون ثني مبكر.'],
            ] : [
                ['title' => 'Hip-hinge pattern', 'explanation' => 'The hips clearly push back, which is the foundation of the deadlift pattern.'],
                ['title' => 'Soft knee bend', 'explanation' => 'The knees stay softly bent without turning the movement into a squat.'],
                ['title' => 'Foot stability', 'explanation' => 'The base of support stays planted throughout the visible movement.'],
                ['title' => 'Straight arms', 'explanation' => 'The arms stay long and carry the load without early elbow bend.'],
            ],
            detectedIssues: $ar ? [
                [
                    'title' => 'تقوس الظهر في الجزء السفلي',
                    'status' => 'attention',
                    'status_label' => 'يحتاج تركيزاً',
                    'explanation' => 'عند الوصول إلى الجزء السفلي من الحركة يظهر تقوس واضح في الظهر بدل الحفاظ على جذع أكثر ثباتاً واستقامة نسبية أثناء المفصلة.',
                    'correction' => 'قلّل مدى النزول قليلاً إلى النقطة التي تستطيع فيها الحفاظ على ظهر أكثر استقراراً، وشد الجذع قبل بدء النزول.',
                ],
                [
                    'title' => 'وضعية الرأس والنظر للأسفل',
                    'status' => 'improve',
                    'status_label' => 'يحتاج تحسيناً',
                    'explanation' => 'في عدة مراحل من الحركة يتجه النظر مباشرة نحو الأرض، ما يساهم في زيادة انحناء الجزء العلوي من الجذع.',
                    'correction' => 'حافظ على الرأس في امتداد طبيعي مع الجذع، وانظر إلى نقطة أمامك على الأرض بمسافة قصيرة بدل النظر مباشرة تحتك.',
                ],
                [
                    'title' => 'قرب الحمل من الجسم',
                    'status' => 'improve',
                    'status_label' => 'يحتاج تحسيناً',
                    'explanation' => 'في بعض لحظات النزول يبدو الحمل أبعد قليلاً عن الساقين، ما يزيد صعوبة الحفاظ على وضعية فعالة للجذع.',
                    'correction' => 'أبقِ الأوزان قريبة من الساقين طوال المسار، وكأنك تمررها بجانب الفخذين أثناء النزول والصعود.',
                ],
            ] : [
                [
                    'title' => 'Back rounding at the bottom',
                    'status' => 'attention',
                    'status_label' => 'Needs focus',
                    'explanation' => 'In the lower portion of the movement the back rounds clearly instead of staying in a more stable, relatively neutral hinge posture.',
                    'correction' => 'Shorten the lowering range slightly to the deepest point where the back can stay more stable, and brace the trunk before you hinge.',
                ],
                [
                    'title' => 'Head position and downward gaze',
                    'status' => 'improve',
                    'status_label' => 'Needs improvement',
                    'explanation' => 'At several points the gaze drops straight toward the floor, which contributes to more upper-trunk rounding.',
                    'correction' => 'Keep the head in a natural line with the trunk and look at a spot on the floor a short distance ahead instead of directly under you.',
                ],
                [
                    'title' => 'Load proximity to the body',
                    'status' => 'improve',
                    'status_label' => 'Needs improvement',
                    'explanation' => 'At moments during the lowering phase the load appears slightly farther from the legs, making an efficient trunk posture harder to keep.',
                    'correction' => 'Keep the weights close to the legs throughout the path, as if brushing past the thighs on the way down and up.',
                ],
            ],
            coachingCues: $ar
                ? [
                    'ادفع الوركين للخلف مع الإبقاء على الجذع أكثر استقراراً.',
                    'لا تنزل أعمق من النقطة التي تحافظ فيها على ظهر ثابت.',
                    'أبقِ الحمل قريباً من الساقين.',
                    'اجعل الرأس امتداداً طبيعياً للجذع.',
                    'ابدأ وأَنه الحركة بتحكم دون فقدان الوضعية.',
                ]
                : [
                    'Push the hips back while keeping the trunk more stable.',
                    'Do not lower past the point where the back stays solid.',
                    'Keep the load close to the legs.',
                    'Keep the head as a natural extension of the trunk.',
                    'Start and finish with control without losing posture.',
                ],
            commonMistakes: $ar ? [
                ['title' => 'تقوس الظهر بدل المفصلة المستقرة', 'explanation' => 'الوصول لعمق أكبر على حساب ثبات الجذع يقلل جودة الحركة.'],
                ['title' => 'النظر مباشرة للأسفل', 'explanation' => 'إسقاط الرأس للأسفل قد يزيد انحناء الجزء العلوي من الجذع.'],
                ['title' => 'ابتعاد الحمل عن الجسم', 'explanation' => 'كلما ابتعد الحمل عن الساقين زادت صعوبة الحفاظ على وضعية فعالة.'],
            ] : [
                ['title' => 'Rounding instead of a stable hinge', 'explanation' => 'Chasing extra depth at the cost of trunk stability reduces movement quality.'],
                ['title' => 'Looking straight down', 'explanation' => 'Dropping the head toward the floor can increase upper-trunk rounding.'],
                ['title' => 'Load drifting away from the body', 'explanation' => 'The farther the load drifts from the legs, the harder an efficient posture becomes.'],
            ],
            drills: $ar ? [
                $this->drill('Hip Hinge Drill', 'تمرين مفصلة الورك', 'ترسيخ نمط المفصلة مع الحفاظ على جذع أكثر استقراراً.', 'مبتدئ', '2–3', '8–10', '45–60 ثانية', ['ضع عصا خفيفة على الظهر إن توفرت للشعور بالاستقامة.', 'ادفع الوركين للخلف مع انحناء خفيف في الركبتين.', 'توقف قبل أي فقدان واضح لثبات الجذع ثم عد للوقوف.']),
                $this->drill('Dowel RDL', 'رفعة رومانية خفيفة مع عصا', 'تحسين الإحساس بثبات الظهر أثناء النزول.', 'مبتدئ–متوسط', '2–3', '6–8', '60–90 ثانية', ['استخدم حملاً خفيفاً جداً أو عصا.', 'أنزل ببطء مع بقاء الحمل قريباً من الساقين.', 'حافظ على الرأس في امتداد الجذع.']),
                $this->drill('Paused Hip Hinge', 'مفصلة ورك مع توقف', 'تعزيز التحكم عند أعمق نقطة آمنة.', 'متوسط', '2–3', '5–8', '60–90 ثانية', ['انزل إلى أعمق نقطة تستطيع تثبيت الجذع فيها.', 'توقف لحظة قصيرة مع شد خفيف للجذع.', 'اصعد بدفع الوركين للأمام بتحكم.']),
                $this->drill('Light Technique Deadlift', 'رفعة مميتة تقنية خفيفة', 'إعادة بناء المسار القريب والتحكم قبل زيادة الحمل.', 'مبتدئ–متوسط', '2–3', '5–8', '60–90 ثانية', ['استخدم حملاً خفيفاً.', 'ركز على ظهر أكثر استقراراً وحمل قريب من الجسم.', 'كرر النمط الجيد قبل التفكير بزيادة الوزن.']),
            ] : [
                $this->drill('Hip Hinge Drill', 'Hip Hinge Drill', 'Reinforce the hinge pattern while keeping the trunk more stable.', 'Beginner', '2–3', '8–10', '45–60 seconds', ['Place a light dowel along the back if available to feel alignment.', 'Push the hips back with a soft knee bend.', 'Stop before any clear loss of trunk stability, then stand up.']),
                $this->drill('Dowel RDL', 'Light Dowel RDL', 'Improve the feel of a more stable back during the lowering phase.', 'Beginner–Intermediate', '2–3', '6–8', '60–90 seconds', ['Use a very light load or a dowel.', 'Lower slowly while keeping the load close to the legs.', 'Keep the head in line with the trunk.']),
                $this->drill('Paused Hip Hinge', 'Paused Hip Hinge', 'Reinforce control at the deepest safe hinge point.', 'Intermediate', '2–3', '5–8', '60–90 seconds', ['Lower only to the deepest point where the trunk stays braced.', 'Pause briefly with a light brace.', 'Drive the hips forward and stand with control.']),
                $this->drill('Light Technique Deadlift', 'Light Technique Deadlift', 'Rebuild a close path and control before adding load.', 'Beginner–Intermediate', '2–3', '5–8', '60–90 seconds', ['Use a light load.', 'Focus on a more stable back and a close path.', 'Repeat good pattern before chasing heavier weight.']),
            ],
            nextAttempt: $ar
                ? [
                    'جهّز مفصلة الورك مع شد خفيف للجذع قبل النزول.',
                    'لا تنزل أعمق من النقطة التي يبقى فيها الظهر أكثر استقراراً.',
                    'أبقِ الأوزان ملامسة تقريباً لمسار الساقين.',
                    'اجعل النظر أمامك قليلاً وليس مباشرة تحت قدميك.',
                    'اصعد بتحكم مع دفع الوركين للأمام دون فقدان الوضعية.',
                ]
                : [
                    'Set the hinge with a light trunk brace before lowering.',
                    'Do not go deeper than the point where the back stays more stable.',
                    'Keep the weights nearly brushing the leg path.',
                    'Look slightly ahead on the floor, not straight under your feet.',
                    'Stand by driving the hips forward without losing posture.',
                ],
            summary: $ar
                ? 'الملاحظة الأبرز في هذا الفيديو هي تقوس الظهر في الجزء السفلي مع النظر للأسفل وابتعاد الحمل أحياناً عن الجسم. حافظ على مفصلة الورك الجيدة، لكن قلّل العمق حتى تستطيع تثبيت الجذع وإبقاء الحمل قريباً.'
                : 'The clearest notes in this video are back rounding at the bottom, a downward gaze, and occasional load drift away from the body. Keep the good hip hinge, but shorten depth until the trunk stays braced and the load stays close.',
        );
    }

    /**
     * @param  list<array{title: string, explanation: string}>  $positivePoints
     * @param  list<array<string, mixed>>  $detectedIssues
     * @param  list<string>  $coachingCues
     * @param  list<array{title: string, explanation: string}>  $commonMistakes
     * @param  list<array<string, mixed>>  $drills
     * @param  list<string>  $nextAttempt
     * @return array<string, mixed>
     */
    private function payload(
        string $exercise,
        string $language,
        string $title,
        string $analysisLabel,
        string $heroSubtitle,
        string $overallStatus,
        string $overallSummary,
        array $positivePoints,
        array $detectedIssues,
        array $coachingCues,
        array $commonMistakes,
        array $drills,
        array $nextAttempt,
        string $summary,
    ): array {
        $disclaimer = $language === 'ar'
            ? 'هذا التحليل تعليمي ولا يُعد تشخيصاً طبياً، ولا يغني عن تقييم مدرب مؤهل أو مختص صحي عند الحاجة.'
            : 'This analysis is educational and is not a medical diagnosis. It does not replace assessment by a qualified coach or healthcare professional when needed.';

        return [
            'success' => true,
            'exercise' => $exercise,
            'repetitions' => 0,
            'duration_seconds' => 0,
            'confidence' => 0,
            'movement_quality' => 'needs_improvement',
            'observations' => [],
            'tips' => [],
            'metrics' => [],
            'provider' => 'motion_demo',
            'frames_analyzed' => 0,
            'demo' => true,
            'structured' => true,
            'title' => $title,
            'analysis_label' => $analysisLabel,
            'hero_subtitle' => $heroSubtitle,
            'overall_status' => $overallStatus,
            'overall_summary' => $overallSummary,
            'positive_points' => $positivePoints,
            'movement_points' => $detectedIssues,
            'coaching_cues' => $coachingCues,
            'common_mistakes' => $commonMistakes,
            'improvement_drills' => $drills,
            'next_attempt' => $nextAttempt,
            'final_summary' => $summary,
            'disclaimer' => $disclaimer,
            'language' => $language,
        ];
    }

    /**
     * @param  list<string>  $instructions
     * @return array<string, mixed>
     */
    private function drill(
        string $id,
        string $name,
        string $purpose,
        string $difficulty,
        string $sets,
        string $reps,
        string $rest,
        array $instructions,
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'purpose' => $purpose,
            'difficulty' => $difficulty,
            'sets' => $sets,
            'reps' => $reps,
            'rest' => $rest,
            'instructions' => $instructions,
        ];
    }
}
