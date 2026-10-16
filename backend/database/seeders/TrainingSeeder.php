<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\ExerciseVideo;
use App\Models\TrainingCategory;
use App\Models\TrainingProgram;
use App\Models\TrainingProgramExercise;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutSet;
use App\Support\BodybuildingExerciseMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the exercise library.
 *
 * Categories are unique per discipline body-part / skill (no level in the slug);
 * the level lives on each exercise via `difficulty`.
 */
class TrainingSeeder extends Seeder
{
    /**
     * Seeded exercise ids, keyed by category slug then English name.
     *
     * @var array<string, array<string, int>>
     */
    private array $ids = [];

    private const EVIDENCE_NOTES = 'Evidence-informed option for this muscle group / movement goal.';

    private const SOURCE_TITLE = 'ACSM Resistance Training Position Stand (contextual)';

    private const SOURCE_YEAR = 2026;

    public function run(): void
    {
        Model::unguarded(function (): void {
            $this->wipe();
            $this->seedBodybuilding();
            $this->seedMma();
            $this->seedHome();
            $this->seedProgressions();
            $this->seedAlternatives();
            $this->seedPrograms();
        });
    }

    /**
     * The library is regenerated from scratch so legacy level-scoped categories
     * (bb-chest-beginner, mma-striking-advanced, ...) cannot linger.
     */
    private function wipe(): void
    {
        WorkoutSet::query()->delete();
        WorkoutSessionExercise::query()->delete();
        WorkoutSession::query()->delete();
        TrainingProgramExercise::query()->delete();
        TrainingProgram::query()->delete();
        ExerciseVideo::query()->delete();
        Exercise::query()->update(['progression_exercise_id' => null, 'regression_exercise_id' => null]);
        Exercise::query()->delete();
        TrainingCategory::query()->delete();
    }

    private function seedBodybuilding(): void
    {
        $defaults = [
            'gym_friendly' => true,
            'home_friendly' => false,
            'bodyweight_supported' => false,
            'mma_specific' => false,
            'recommended_rir' => 2,
            'instructions_en' => 'Control the eccentric, own the full range you can hold, and stop each set with 1-3 reps in reserve.',
            'instructions_ar' => 'تحكّم في مرحلة النزول، والتزم بالمدى الحركي الذي تستطيع التحكم به، وأنهِ كل مجموعة مع بقاء 1-3 تكرارات في المخزون.',
            'safety_notes_en' => 'Warm up progressively and use a spotter or safety pins on heavy barbell work.',
            'safety_notes_ar' => 'قم بإحماء تدريجي واستخدم مساعداً أو قضبان أمان عند العمل بأوزان ثقيلة.',
        ];

        $this->seedCategories('bodybuilding', 'bb', 100, $defaults, [
            [
                'slug' => 'chest',
                'en' => 'Chest',
                'ar' => 'الصدر',
                'desc_en' => 'Horizontal and incline pressing plus fly variations for the pectorals.',
                'desc_ar' => 'تمارين الدفع الأفقي والمائل إضافة إلى التفتيح لعضلات الصدر.',
                'exercises' => [
                    ['name_en' => 'Barbell Bench Press', 'name_ar' => 'ضغط البار المسطح', 'difficulty' => 'intermediate', 'equipment' => 'barbell', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'front deltoids'], 'movement_pattern' => 'horizontal_push', 'sets' => 4, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 150],
                    ['name_en' => 'Incline Barbell Bench Press', 'name_ar' => 'ضغط البار المائل', 'difficulty' => 'intermediate', 'equipment' => 'barbell', 'primary_muscles' => ['upper chest'], 'secondary_muscles' => ['front deltoids', 'triceps'], 'movement_pattern' => 'horizontal_push', 'sets' => 4, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 120],
                    ['name_en' => 'Incline Dumbbell Press', 'name_ar' => 'ضغط الدمبل المائل', 'difficulty' => 'intermediate', 'equipment' => 'dumbbells', 'primary_muscles' => ['upper chest'], 'secondary_muscles' => ['front deltoids', 'triceps'], 'movement_pattern' => 'horizontal_push', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Flat Dumbbell Press', 'name_ar' => 'ضغط الدمبل المسطح', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'front deltoids'], 'movement_pattern' => 'horizontal_push', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Machine Chest Press', 'name_ar' => 'ضغط الصدر على الجهاز', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps'], 'movement_pattern' => 'horizontal_push', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 75],
                    ['name_en' => 'Cable Chest Fly', 'name_ar' => 'تفتيح الصدر بالكابل', 'difficulty' => 'intermediate', 'equipment' => 'cable', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['front deltoids'], 'movement_pattern' => 'horizontal_adduction', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Pec-Deck Machine Fly', 'name_ar' => 'تفتيح الصدر على جهاز البيك ديك', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['chest'], 'secondary_muscles' => [], 'movement_pattern' => 'horizontal_adduction', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Weighted Chest Dip', 'name_ar' => 'الغطس على المتوازي بأوزان', 'difficulty' => 'advanced', 'equipment' => 'dip bars', 'primary_muscles' => ['lower chest'], 'secondary_muscles' => ['triceps', 'front deltoids'], 'movement_pattern' => 'vertical_push', 'sets' => 4, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 120],
                    ['name_en' => 'Push-Up', 'name_ar' => 'تمرين الضغط', 'difficulty' => 'beginner', 'equipment' => 'bodyweight', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'core'], 'movement_pattern' => 'horizontal_push', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 20, 'rest_seconds' => 60],
                ],
            ],
            [
                'slug' => 'back',
                'en' => 'Back',
                'ar' => 'الظهر',
                'desc_en' => 'Vertical pulls, rows and hinge work for the lats, traps and spinal erectors.',
                'desc_ar' => 'السحب العمودي والتجديف وتمارين المفصلة لعضلات الظهر العريضة والدالية والمثبتة للعمود.',
                'exercises' => [
                    ['name_en' => 'Conventional Deadlift', 'name_ar' => 'الرفعة الميتة التقليدية', 'difficulty' => 'advanced', 'equipment' => 'barbell', 'primary_muscles' => ['back', 'hamstrings'], 'secondary_muscles' => ['glutes', 'forearms'], 'movement_pattern' => 'hinge', 'sets' => 4, 'reps_min' => 3, 'reps_max' => 6, 'rest_seconds' => 180],
                    ['name_en' => 'Pull-Up', 'name_ar' => 'العقلة', 'difficulty' => 'intermediate', 'equipment' => 'pull-up bar', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['lats'], 'secondary_muscles' => ['biceps', 'mid back'], 'movement_pattern' => 'vertical_pull', 'sets' => 4, 'reps_min' => 5, 'reps_max' => 10, 'rest_seconds' => 120],
                    ['name_en' => 'Lat Pulldown', 'name_ar' => 'السحب العلوي (البولي)', 'difficulty' => 'beginner', 'equipment' => 'cable', 'primary_muscles' => ['lats'], 'secondary_muscles' => ['biceps'], 'movement_pattern' => 'vertical_pull', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Barbell Bent-Over Row', 'name_ar' => 'التجديف بالبار المنحني', 'difficulty' => 'intermediate', 'equipment' => 'barbell', 'primary_muscles' => ['mid back'], 'secondary_muscles' => ['lats', 'biceps'], 'movement_pattern' => 'horizontal_pull', 'sets' => 4, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 120],
                    ['name_en' => 'One-Arm Dumbbell Row', 'name_ar' => 'تجديف الدمبل بذراع واحدة', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'primary_muscles' => ['lats'], 'secondary_muscles' => ['mid back', 'biceps'], 'movement_pattern' => 'horizontal_pull', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'Seated Cable Row', 'name_ar' => 'التجديف الأرضي بالكابل', 'difficulty' => 'beginner', 'equipment' => 'cable', 'primary_muscles' => ['mid back'], 'secondary_muscles' => ['lats', 'biceps'], 'movement_pattern' => 'horizontal_pull', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Chest-Supported T-Bar Row', 'name_ar' => 'تجديف التي-بار بإسناد الصدر', 'difficulty' => 'intermediate', 'equipment' => 'machine', 'primary_muscles' => ['mid back'], 'secondary_muscles' => ['lats', 'rear deltoids'], 'movement_pattern' => 'horizontal_pull', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Straight-Arm Pulldown', 'name_ar' => 'السحب بذراعين ممدودتين', 'difficulty' => 'intermediate', 'equipment' => 'cable', 'primary_muscles' => ['lats'], 'secondary_muscles' => ['core'], 'movement_pattern' => 'vertical_pull', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Barbell Shrug', 'name_ar' => 'رفع الكتفين بالبار', 'difficulty' => 'beginner', 'equipment' => 'barbell', 'primary_muscles' => ['upper traps'], 'secondary_muscles' => ['forearms'], 'movement_pattern' => 'shrug', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 75],
                ],
            ],
            [
                'slug' => 'shoulders',
                'en' => 'Shoulders',
                'ar' => 'الأكتاف',
                'desc_en' => 'Overhead pressing with lateral and rear deltoid isolation.',
                'desc_ar' => 'الضغط فوق الرأس مع عزل الرأس الجانبي والخلفي للكتف.',
                'exercises' => [
                    ['name_en' => 'Standing Overhead Press', 'name_ar' => 'الضغط العسكري واقفاً', 'difficulty' => 'intermediate', 'equipment' => 'barbell', 'primary_muscles' => ['front deltoids'], 'secondary_muscles' => ['triceps', 'core'], 'movement_pattern' => 'vertical_push', 'sets' => 4, 'reps_min' => 5, 'reps_max' => 8, 'rest_seconds' => 150],
                    ['name_en' => 'Seated Dumbbell Shoulder Press', 'name_ar' => 'ضغط الأكتاف بالدمبل جالساً', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'primary_muscles' => ['front deltoids'], 'secondary_muscles' => ['triceps'], 'movement_pattern' => 'vertical_push', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Dumbbell Lateral Raise', 'name_ar' => 'الرفرفة الجانبية بالدمبل', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'primary_muscles' => ['side deltoids'], 'secondary_muscles' => [], 'movement_pattern' => 'abduction', 'sets' => 4, 'reps_min' => 12, 'reps_max' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Cable Lateral Raise', 'name_ar' => 'الرفرفة الجانبية بالكابل', 'difficulty' => 'intermediate', 'equipment' => 'cable', 'primary_muscles' => ['side deltoids'], 'secondary_muscles' => [], 'movement_pattern' => 'abduction', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Reverse Pec-Deck Fly', 'name_ar' => 'التفتيح الخلفي على الجهاز', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['rear deltoids'], 'secondary_muscles' => ['mid back'], 'movement_pattern' => 'horizontal_pull', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Cable Face Pull', 'name_ar' => 'سحب الوجه بالحبل', 'difficulty' => 'beginner', 'equipment' => 'cable', 'primary_muscles' => ['rear deltoids'], 'secondary_muscles' => ['mid back', 'rotator cuff'], 'movement_pattern' => 'horizontal_pull', 'sets' => 3, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Arnold Press', 'name_ar' => 'ضغط أرنولد', 'difficulty' => 'intermediate', 'equipment' => 'dumbbells', 'primary_muscles' => ['front deltoids'], 'secondary_muscles' => ['side deltoids', 'triceps'], 'movement_pattern' => 'vertical_push', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Landmine Press', 'name_ar' => 'ضغط اللاندماين', 'difficulty' => 'intermediate', 'equipment' => 'barbell', 'primary_muscles' => ['front deltoids'], 'secondary_muscles' => ['upper chest', 'core'], 'movement_pattern' => 'vertical_push', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 90],
                ],
            ],
            [
                'slug' => 'biceps',
                'en' => 'Biceps',
                'ar' => 'العضلة ذات الرأسين',
                'desc_en' => 'Elbow flexion at short, mid and long muscle lengths.',
                'desc_ar' => 'ثني المرفق عند أطوال عضلية قصيرة ومتوسطة وطويلة.',
                'exercises' => [
                    ['name_en' => 'Barbell Curl', 'name_ar' => 'ثني الذراعين بالبار', 'difficulty' => 'beginner', 'equipment' => 'barbell', 'primary_muscles' => ['biceps'], 'secondary_muscles' => ['forearms'], 'movement_pattern' => 'elbow_flexion', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'EZ-Bar Curl', 'name_ar' => 'ثني الذراعين ببار الـ EZ', 'difficulty' => 'beginner', 'equipment' => 'ez bar', 'primary_muscles' => ['biceps'], 'secondary_muscles' => ['forearms'], 'movement_pattern' => 'elbow_flexion', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'Incline Dumbbell Curl', 'name_ar' => 'ثني الدمبل على مقعد مائل', 'difficulty' => 'intermediate', 'equipment' => 'dumbbells', 'primary_muscles' => ['biceps long head'], 'secondary_muscles' => ['forearms'], 'movement_pattern' => 'elbow_flexion', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'Hammer Curl', 'name_ar' => 'ثني المطرقة', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'primary_muscles' => ['brachialis'], 'secondary_muscles' => ['biceps', 'forearms'], 'movement_pattern' => 'elbow_flexion', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Cable Curl', 'name_ar' => 'ثني الذراعين بالكابل', 'difficulty' => 'beginner', 'equipment' => 'cable', 'primary_muscles' => ['biceps'], 'secondary_muscles' => ['forearms'], 'movement_pattern' => 'elbow_flexion', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Preacher Curl', 'name_ar' => 'ثني الواعظ (البريتشر)', 'difficulty' => 'intermediate', 'equipment' => 'ez bar', 'primary_muscles' => ['biceps short head'], 'secondary_muscles' => ['forearms'], 'movement_pattern' => 'elbow_flexion', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'Chin-Up', 'name_ar' => 'العقلة بقبضة عكسية', 'difficulty' => 'intermediate', 'equipment' => 'pull-up bar', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['biceps'], 'secondary_muscles' => ['lats'], 'movement_pattern' => 'vertical_pull', 'sets' => 3, 'reps_min' => 5, 'reps_max' => 10, 'rest_seconds' => 120],
                ],
            ],
            [
                'slug' => 'triceps',
                'en' => 'Triceps',
                'ar' => 'العضلة ثلاثية الرؤوس',
                'desc_en' => 'Pressing and extension work across all three triceps heads.',
                'desc_ar' => 'تمارين الدفع والتمديد لتغطية رؤوس العضلة الثلاثية الثلاثة.',
                'exercises' => [
                    ['name_en' => 'Close-Grip Bench Press', 'name_ar' => 'ضغط البار بقبضة ضيقة', 'difficulty' => 'intermediate', 'equipment' => 'barbell', 'primary_muscles' => ['triceps'], 'secondary_muscles' => ['chest', 'front deltoids'], 'movement_pattern' => 'horizontal_push', 'sets' => 4, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 120],
                    ['name_en' => 'Triceps Rope Pushdown', 'name_ar' => 'دفع الحبل للأسفل للترايسبس', 'difficulty' => 'beginner', 'equipment' => 'cable', 'primary_muscles' => ['triceps'], 'secondary_muscles' => [], 'movement_pattern' => 'elbow_extension', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Overhead Cable Triceps Extension', 'name_ar' => 'تمديد الترايسبس خلف الرأس بالكابل', 'difficulty' => 'intermediate', 'equipment' => 'cable', 'primary_muscles' => ['triceps long head'], 'secondary_muscles' => [], 'movement_pattern' => 'elbow_extension', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 75],
                    ['name_en' => 'Lying Triceps Extension (Skull Crusher)', 'name_ar' => 'تمديد الترايسبس مستلقياً (سكل كراشر)', 'difficulty' => 'intermediate', 'equipment' => 'ez bar', 'primary_muscles' => ['triceps'], 'secondary_muscles' => [], 'movement_pattern' => 'elbow_extension', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Dumbbell Overhead Extension', 'name_ar' => 'تمديد الدمبل خلف الرأس', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'primary_muscles' => ['triceps long head'], 'secondary_muscles' => [], 'movement_pattern' => 'elbow_extension', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'Parallel Bar Dip', 'name_ar' => 'الغطس على المتوازي', 'difficulty' => 'intermediate', 'equipment' => 'dip bars', 'bodyweight_supported' => true, 'primary_muscles' => ['triceps'], 'secondary_muscles' => ['chest', 'front deltoids'], 'movement_pattern' => 'vertical_push', 'sets' => 3, 'reps_min' => 6, 'reps_max' => 12, 'rest_seconds' => 120],
                    ['name_en' => 'Bench Dip', 'name_ar' => 'الغطس على المقعد', 'difficulty' => 'beginner', 'equipment' => 'bench', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['triceps'], 'secondary_muscles' => ['front deltoids'], 'movement_pattern' => 'vertical_push', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                ],
            ],
            [
                'slug' => 'forearms',
                'en' => 'Forearms & Grip',
                'ar' => 'الساعدان وقوة القبضة',
                'desc_en' => 'Wrist flexion, extension and loaded-carry grip work.',
                'desc_ar' => 'ثني ومد الرسغ وتمارين القبضة تحت الحمل.',
                'exercises' => [
                    ['name_en' => 'Barbell Wrist Curl', 'name_ar' => 'ثني الرسغ بالبار', 'difficulty' => 'beginner', 'equipment' => 'barbell', 'primary_muscles' => ['wrist flexors'], 'secondary_muscles' => [], 'movement_pattern' => 'wrist_flexion', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Barbell Reverse Wrist Curl', 'name_ar' => 'ثني الرسغ العكسي بالبار', 'difficulty' => 'beginner', 'equipment' => 'barbell', 'primary_muscles' => ['wrist extensors'], 'secondary_muscles' => [], 'movement_pattern' => 'wrist_extension', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Reverse Curl', 'name_ar' => 'الثني العكسي للذراعين', 'difficulty' => 'beginner', 'equipment' => 'ez bar', 'primary_muscles' => ['brachioradialis'], 'secondary_muscles' => ['biceps'], 'movement_pattern' => 'elbow_flexion', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => "Farmer's Carry", 'name_ar' => 'حمل المزارع', 'difficulty' => 'intermediate', 'equipment' => 'dumbbells', 'primary_muscles' => ['forearms'], 'secondary_muscles' => ['traps', 'core'], 'movement_pattern' => 'carry', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 3, 'duration_seconds' => 40, 'rest_seconds' => 90],
                    ['name_en' => 'Dead Hang', 'name_ar' => 'التعلق الساكن على العقلة', 'difficulty' => 'beginner', 'equipment' => 'pull-up bar', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['forearms'], 'secondary_muscles' => ['lats', 'shoulders'], 'movement_pattern' => 'hang', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 30, 'rest_seconds' => 60],
                    ['name_en' => 'Plate Pinch Hold', 'name_ar' => 'قبض الأثقال بالأصابع', 'difficulty' => 'intermediate', 'equipment' => 'weight plates', 'primary_muscles' => ['grip'], 'secondary_muscles' => ['forearms'], 'movement_pattern' => 'grip', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 30, 'rest_seconds' => 60],
                ],
            ],
            [
                'slug' => 'quads',
                'en' => 'Quadriceps',
                'ar' => 'العضلة رباعية الرؤوس',
                'desc_en' => 'Knee-dominant squat, press and extension patterns.',
                'desc_ar' => 'أنماط القرفصاء والضغط والتمديد التي تعتمد على الركبة.',
                'exercises' => [
                    ['name_en' => 'Barbell Back Squat', 'name_ar' => 'القرفصاء الخلفي بالبار', 'difficulty' => 'intermediate', 'equipment' => 'barbell', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes', 'core'], 'movement_pattern' => 'squat', 'sets' => 4, 'reps_min' => 5, 'reps_max' => 8, 'rest_seconds' => 180],
                    ['name_en' => 'Front Squat', 'name_ar' => 'القرفصاء الأمامي', 'difficulty' => 'advanced', 'equipment' => 'barbell', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['core', 'upper back'], 'movement_pattern' => 'squat', 'sets' => 4, 'reps_min' => 5, 'reps_max' => 8, 'rest_seconds' => 150],
                    ['name_en' => 'Leg Press', 'name_ar' => 'ضغط الأرجل على الجهاز', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes'], 'movement_pattern' => 'squat', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 15, 'rest_seconds' => 120],
                    ['name_en' => 'Hack Squat', 'name_ar' => 'قرفصاء الهاك', 'difficulty' => 'intermediate', 'equipment' => 'machine', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes'], 'movement_pattern' => 'squat', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 120],
                    ['name_en' => 'Bulgarian Split Squat', 'name_ar' => 'القرفصاء البلغاري', 'difficulty' => 'intermediate', 'equipment' => 'dumbbells', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes', 'adductors'], 'movement_pattern' => 'lunge', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Walking Lunge', 'name_ar' => 'الاندفاع المتحرك', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes'], 'movement_pattern' => 'lunge', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Leg Extension', 'name_ar' => 'تمديد الساقين على الجهاز', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => [], 'movement_pattern' => 'knee_extension', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Goblet Squat', 'name_ar' => 'قرفصاء الكأس', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'bodyweight_supported' => false, 'home_friendly' => true, 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes', 'core'], 'movement_pattern' => 'squat', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 75],
                ],
            ],
            [
                'slug' => 'hamstrings',
                'en' => 'Hamstrings',
                'ar' => 'أوتار الركبة الخلفية',
                'desc_en' => 'Hip-hinge and knee-flexion work for the posterior thigh.',
                'desc_ar' => 'تمارين مفصلة الورك وثني الركبة للجزء الخلفي من الفخذ.',
                'exercises' => [
                    ['name_en' => 'Romanian Deadlift', 'name_ar' => 'الرفعة الميتة الرومانية', 'difficulty' => 'intermediate', 'equipment' => 'barbell', 'primary_muscles' => ['hamstrings'], 'secondary_muscles' => ['glutes', 'lower back'], 'movement_pattern' => 'hinge', 'sets' => 4, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 120],
                    ['name_en' => 'Lying Leg Curl', 'name_ar' => 'ثني الساقين مستلقياً', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['hamstrings'], 'secondary_muscles' => ['calves'], 'movement_pattern' => 'knee_flexion', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 75],
                    ['name_en' => 'Seated Leg Curl', 'name_ar' => 'ثني الساقين جالساً', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['hamstrings'], 'secondary_muscles' => [], 'movement_pattern' => 'knee_flexion', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 75],
                    ['name_en' => 'Stiff-Leg Dumbbell Deadlift', 'name_ar' => 'الرفعة الميتة بساق مستقيمة بالدمبل', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'primary_muscles' => ['hamstrings'], 'secondary_muscles' => ['glutes'], 'movement_pattern' => 'hinge', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Barbell Good Morning', 'name_ar' => 'تمرين الصباح الجيد بالبار', 'difficulty' => 'advanced', 'equipment' => 'barbell', 'primary_muscles' => ['hamstrings'], 'secondary_muscles' => ['glutes', 'lower back'], 'movement_pattern' => 'hinge', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 120],
                    ['name_en' => 'Nordic Hamstring Curl', 'name_ar' => 'ثني النورديك لأوتار الركبة', 'difficulty' => 'advanced', 'equipment' => 'bodyweight', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['hamstrings'], 'secondary_muscles' => ['calves'], 'movement_pattern' => 'knee_flexion', 'sets' => 3, 'reps_min' => 4, 'reps_max' => 8, 'rest_seconds' => 120],
                    ['name_en' => 'Single-Leg Romanian Deadlift', 'name_ar' => 'الرفعة الميتة الرومانية على ساق واحدة', 'difficulty' => 'intermediate', 'equipment' => 'dumbbells', 'primary_muscles' => ['hamstrings'], 'secondary_muscles' => ['glutes', 'core'], 'movement_pattern' => 'hinge', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => '45-Degree Back Extension', 'name_ar' => 'تمديد الظهر بزاوية 45 درجة', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['hamstrings'], 'secondary_muscles' => ['glutes', 'lower back'], 'movement_pattern' => 'hinge', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                ],
            ],
            [
                'slug' => 'glutes',
                'en' => 'Glutes',
                'ar' => 'عضلات الأرداف',
                'desc_en' => 'Hip extension and abduction loaded at both short and long muscle lengths.',
                'desc_ar' => 'مد الورك وتبعيده بأحمال عند أطوال عضلية قصيرة وطويلة.',
                'exercises' => [
                    ['name_en' => 'Barbell Hip Thrust', 'name_ar' => 'دفع الورك بالبار', 'difficulty' => 'intermediate', 'equipment' => 'barbell', 'primary_muscles' => ['glutes'], 'secondary_muscles' => ['hamstrings'], 'movement_pattern' => 'hip_extension', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 120],
                    ['name_en' => 'Glute Bridge', 'name_ar' => 'جسر الأرداف', 'difficulty' => 'beginner', 'equipment' => 'bodyweight', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['glutes'], 'secondary_muscles' => ['hamstrings'], 'movement_pattern' => 'hip_extension', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Sumo Deadlift', 'name_ar' => 'الرفعة الميتة السومو', 'difficulty' => 'advanced', 'equipment' => 'barbell', 'primary_muscles' => ['glutes'], 'secondary_muscles' => ['quadriceps', 'adductors', 'back'], 'movement_pattern' => 'hinge', 'sets' => 4, 'reps_min' => 4, 'reps_max' => 6, 'rest_seconds' => 180],
                    ['name_en' => 'Dumbbell Step-Up', 'name_ar' => 'الصعود على الصندوق بالدمبل', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'primary_muscles' => ['glutes'], 'secondary_muscles' => ['quadriceps'], 'movement_pattern' => 'lunge', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'Cable Glute Kickback', 'name_ar' => 'رفس الكابل للخلف للأرداف', 'difficulty' => 'beginner', 'equipment' => 'cable', 'primary_muscles' => ['glutes'], 'secondary_muscles' => ['hamstrings'], 'movement_pattern' => 'hip_extension', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Hip Abduction Machine', 'name_ar' => 'تبعيد الورك على الجهاز', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['gluteus medius'], 'secondary_muscles' => [], 'movement_pattern' => 'abduction', 'sets' => 3, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Dumbbell Reverse Lunge', 'name_ar' => 'الاندفاع الخلفي بالدمبل', 'difficulty' => 'beginner', 'equipment' => 'dumbbells', 'primary_muscles' => ['glutes'], 'secondary_muscles' => ['quadriceps'], 'movement_pattern' => 'lunge', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'Banded Frog Pump', 'name_ar' => 'ضخ الضفدع بالمطاط', 'difficulty' => 'beginner', 'equipment' => 'resistance band', 'home_friendly' => true, 'primary_muscles' => ['glutes'], 'secondary_muscles' => [], 'movement_pattern' => 'hip_extension', 'sets' => 3, 'reps_min' => 20, 'reps_max' => 30, 'rest_seconds' => 45],
                ],
            ],
            [
                'slug' => 'calves',
                'en' => 'Calves',
                'ar' => 'عضلات السمانة',
                'desc_en' => 'Straight-leg and bent-knee plantar flexion plus tibialis balance work.',
                'desc_ar' => 'مد القدم بالساق المستقيمة والمثنية مع عمل موازن للعضلة الظنبوبية.',
                'exercises' => [
                    ['name_en' => 'Standing Calf Raise', 'name_ar' => 'رفع السمانة واقفاً', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['gastrocnemius'], 'secondary_muscles' => ['soleus'], 'movement_pattern' => 'plantar_flexion', 'sets' => 4, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Seated Calf Raise', 'name_ar' => 'رفع السمانة جالساً', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['soleus'], 'secondary_muscles' => [], 'movement_pattern' => 'plantar_flexion', 'sets' => 4, 'reps_min' => 12, 'reps_max' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Leg Press Calf Press', 'name_ar' => 'ضغط السمانة على جهاز ضغط الأرجل', 'difficulty' => 'beginner', 'equipment' => 'machine', 'primary_muscles' => ['gastrocnemius'], 'secondary_muscles' => ['soleus'], 'movement_pattern' => 'plantar_flexion', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Single-Leg Dumbbell Calf Raise', 'name_ar' => 'رفع السمانة على ساق واحدة بالدمبل', 'difficulty' => 'intermediate', 'equipment' => 'dumbbells', 'home_friendly' => true, 'primary_muscles' => ['gastrocnemius'], 'secondary_muscles' => ['ankle stabilisers'], 'movement_pattern' => 'plantar_flexion', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Donkey Calf Raise', 'name_ar' => 'رفع السمانة بوضعية الحمار', 'difficulty' => 'intermediate', 'equipment' => 'machine', 'primary_muscles' => ['gastrocnemius'], 'secondary_muscles' => [], 'movement_pattern' => 'plantar_flexion', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Tibialis Raise', 'name_ar' => 'رفع العضلة الظنبوبية', 'difficulty' => 'beginner', 'equipment' => 'bodyweight', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['tibialis anterior'], 'secondary_muscles' => [], 'movement_pattern' => 'dorsiflexion', 'sets' => 3, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                ],
            ],
            [
                'slug' => 'core',
                'en' => 'Core & Abs',
                'ar' => 'البطن والجذع',
                'desc_en' => 'Anti-extension, anti-rotation and loaded flexion for the trunk.',
                'desc_ar' => 'مقاومة المد ومقاومة الدوران والانثناء تحت الحمل لعضلات الجذع.',
                'exercises' => [
                    ['name_en' => 'Hanging Leg Raise', 'name_ar' => 'رفع الرجلين معلقاً', 'difficulty' => 'advanced', 'equipment' => 'pull-up bar', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['rectus abdominis'], 'secondary_muscles' => ['hip flexors', 'forearms'], 'movement_pattern' => 'trunk_flexion', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 15, 'rest_seconds' => 75],
                    ['name_en' => 'Cable Crunch', 'name_ar' => 'طي الجذع بالكابل', 'difficulty' => 'intermediate', 'equipment' => 'cable', 'primary_muscles' => ['rectus abdominis'], 'secondary_muscles' => ['obliques'], 'movement_pattern' => 'trunk_flexion', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Plank', 'name_ar' => 'تمرين البلانك', 'difficulty' => 'beginner', 'equipment' => 'bodyweight', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['core'], 'secondary_muscles' => ['shoulders', 'glutes'], 'movement_pattern' => 'anti_extension', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 45, 'rest_seconds' => 45],
                    ['name_en' => 'Side Plank', 'name_ar' => 'البلانك الجانبي', 'difficulty' => 'beginner', 'equipment' => 'bodyweight', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['obliques'], 'secondary_muscles' => ['gluteus medius'], 'movement_pattern' => 'anti_lateral_flexion', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 30, 'rest_seconds' => 45],
                    ['name_en' => 'Ab Wheel Rollout', 'name_ar' => 'دحرجة عجلة البطن', 'difficulty' => 'advanced', 'equipment' => 'ab wheel', 'home_friendly' => true, 'primary_muscles' => ['rectus abdominis'], 'secondary_muscles' => ['lats', 'hip flexors'], 'movement_pattern' => 'anti_extension', 'sets' => 3, 'reps_min' => 6, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Pallof Press', 'name_ar' => 'ضغط بالوف المقاوم للدوران', 'difficulty' => 'intermediate', 'equipment' => 'cable', 'primary_muscles' => ['obliques'], 'secondary_muscles' => ['core'], 'movement_pattern' => 'anti_rotation', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 60],
                    ['name_en' => 'Weighted Decline Sit-Up', 'name_ar' => 'الجلوس من الرقود على مقعد مائل بأوزان', 'difficulty' => 'intermediate', 'equipment' => 'bench', 'primary_muscles' => ['rectus abdominis'], 'secondary_muscles' => ['hip flexors'], 'movement_pattern' => 'trunk_flexion', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 75],
                    ['name_en' => 'Dead Bug', 'name_ar' => 'تمرين الحشرة الميتة', 'difficulty' => 'beginner', 'equipment' => 'bodyweight', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['core'], 'secondary_muscles' => ['hip flexors'], 'movement_pattern' => 'anti_extension', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 45],
                ],
            ],
            [
                'slug' => 'full-body',
                'en' => 'Full Body',
                'ar' => 'الجسم الكامل',
                'desc_en' => 'Multi-joint lifts and complexes that load several patterns in one movement.',
                'desc_ar' => 'رفعات ومركبات متعددة المفاصل تحمّل أكثر من نمط حركي في تمرين واحد.',
                'exercises' => [
                    ['name_en' => 'Trap Bar Deadlift', 'name_ar' => 'الرفعة الميتة ببار الترابيزي', 'difficulty' => 'intermediate', 'equipment' => 'trap bar', 'primary_muscles' => ['glutes', 'quadriceps'], 'secondary_muscles' => ['back', 'forearms'], 'movement_pattern' => 'hinge', 'sets' => 4, 'reps_min' => 5, 'reps_max' => 8, 'rest_seconds' => 150],
                    ['name_en' => 'Barbell Power Clean', 'name_ar' => 'رفعة النطر (الكلين) بالبار', 'difficulty' => 'advanced', 'equipment' => 'barbell', 'primary_muscles' => ['glutes', 'hamstrings'], 'secondary_muscles' => ['traps', 'quadriceps'], 'movement_pattern' => 'olympic_pull', 'sets' => 5, 'reps_min' => 2, 'reps_max' => 3, 'rest_seconds' => 180],
                    ['name_en' => 'Barbell Thruster', 'name_ar' => 'الثرستر بالبار', 'difficulty' => 'intermediate', 'equipment' => 'barbell', 'primary_muscles' => ['quadriceps', 'shoulders'], 'secondary_muscles' => ['core', 'triceps'], 'movement_pattern' => 'squat_to_press', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 120],
                    ['name_en' => 'Dumbbell Snatch', 'name_ar' => 'الخطف بالدمبل', 'difficulty' => 'advanced', 'equipment' => 'dumbbells', 'primary_muscles' => ['glutes', 'shoulders'], 'secondary_muscles' => ['back', 'core'], 'movement_pattern' => 'olympic_pull', 'sets' => 4, 'reps_min' => 4, 'reps_max' => 6, 'rest_seconds' => 90],
                    ['name_en' => 'Kettlebell Swing', 'name_ar' => 'أرجحة الكيتل بيل', 'difficulty' => 'intermediate', 'equipment' => 'kettlebell', 'home_friendly' => true, 'primary_muscles' => ['glutes', 'hamstrings'], 'secondary_muscles' => ['core', 'forearms'], 'movement_pattern' => 'hinge', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 75],
                    ['name_en' => 'Dumbbell Renegade Row', 'name_ar' => 'تجديف الرينيجيد بالدمبل', 'difficulty' => 'intermediate', 'equipment' => 'dumbbells', 'home_friendly' => true, 'primary_muscles' => ['back', 'core'], 'secondary_muscles' => ['shoulders', 'triceps'], 'movement_pattern' => 'horizontal_pull', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 75],
                    ['name_en' => 'Sled Push', 'name_ar' => 'دفع الزلاجة', 'difficulty' => 'intermediate', 'equipment' => 'sled', 'primary_muscles' => ['quadriceps', 'glutes'], 'secondary_muscles' => ['calves', 'core'], 'movement_pattern' => 'push_carry', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 4, 'duration_seconds' => 30, 'rest_seconds' => 90],
                    ['name_en' => 'Burpee', 'name_ar' => 'تمرين البيربي', 'difficulty' => 'beginner', 'equipment' => 'bodyweight', 'bodyweight_supported' => true, 'home_friendly' => true, 'primary_muscles' => ['full body'], 'secondary_muscles' => ['chest', 'quadriceps'], 'movement_pattern' => 'full_body', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                ],
            ],
        ]);
    }

    private function seedMma(): void
    {
        $defaults = [
            'gym_friendly' => true,
            'home_friendly' => true,
            'bodyweight_supported' => true,
            'mma_specific' => true,
            'equipment' => 'none',
            'target' => 'technique',
            'primary_muscles' => ['full body'],
            'secondary_muscles' => [],
            'instructions_en' => 'Drill for quality first: keep your guard up, breathe on every strike, and return to stance after each rep.',
            'instructions_ar' => 'ركّز على جودة الأداء أولاً: حافظ على حراستك، تنفّس مع كل ضربة، وعُد إلى وقفة القتال بعد كل تكرار.',
            'safety_notes_en' => 'Wrap your hands, use pads or a partner for contact work, and never drill at full power without protection.',
            'safety_notes_ar' => 'لفّ يديك، واستخدم الواقيات أو شريكاً عند التدريب التلامسي، ولا تتدرب بأقصى قوة دون حماية.',
        ];

        $this->seedCategories('mma', 'mma', 300, $defaults, [
            [
                'slug' => 'striking',
                'en' => 'Striking',
                'ar' => 'الضربات',
                'desc_en' => 'Boxing fundamentals and hand combinations for MMA range.',
                'desc_ar' => 'أساسيات الملاكمة وتركيبات اليدين ضمن مسافة قتال الفنون المختلطة.',
                'exercises' => [
                    ['name_en' => 'Jab', 'name_ar' => 'اللكمة المستقيمة الأمامية (الجاب)', 'difficulty' => 'beginner', 'equipment' => 'gloves', 'movement_pattern' => 'straight_punch', 'sets' => 4, 'reps_min' => 20, 'reps_max' => 30, 'rest_seconds' => 45],
                    ['name_en' => 'Cross', 'name_ar' => 'اللكمة الخلفية المستقيمة (الكروس)', 'difficulty' => 'beginner', 'equipment' => 'gloves', 'movement_pattern' => 'straight_punch', 'sets' => 4, 'reps_min' => 20, 'reps_max' => 30, 'rest_seconds' => 45],
                    ['name_en' => 'Lead Hook', 'name_ar' => 'الخطاف الأمامي (الهوك)', 'difficulty' => 'intermediate', 'equipment' => 'gloves', 'movement_pattern' => 'hook_punch', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Rear Uppercut', 'name_ar' => 'اللكمة الصاعدة الخلفية (الأبركت)', 'difficulty' => 'intermediate', 'equipment' => 'gloves', 'movement_pattern' => 'uppercut', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Overhand Right', 'name_ar' => 'اللكمة المقوسة العلوية (الأوفرهاند)', 'difficulty' => 'advanced', 'equipment' => 'gloves', 'movement_pattern' => 'looping_punch', 'sets' => 4, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Jab-Cross (1-2) Combination', 'name_ar' => 'تركيبة الجاب والكروس (1-2)', 'difficulty' => 'beginner', 'equipment' => 'gloves', 'movement_pattern' => 'combination', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 4, 'round_seconds' => 120, 'round_rest_seconds' => 60],
                    ['name_en' => 'Jab-Cross-Lead Hook (1-2-3) Combination', 'name_ar' => 'تركيبة الجاب والكروس والخطاف (1-2-3)', 'difficulty' => 'intermediate', 'equipment' => 'gloves', 'movement_pattern' => 'combination', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 5, 'round_seconds' => 120, 'round_rest_seconds' => 60],
                    ['name_en' => 'Shadow Boxing Round', 'name_ar' => 'جولة ملاكمة الظل', 'difficulty' => 'beginner', 'equipment' => 'none', 'movement_pattern' => 'combination', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 3, 'round_seconds' => 180, 'round_rest_seconds' => 60],
                    ['name_en' => 'Heavy Bag Power Round', 'name_ar' => 'جولة قوة على الكيس الثقيل', 'difficulty' => 'advanced', 'equipment' => 'heavy bag', 'movement_pattern' => 'combination', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 5, 'round_seconds' => 180, 'round_rest_seconds' => 60],
                ],
            ],
            [
                'slug' => 'kicks',
                'en' => 'Kicks & Knees',
                'ar' => 'الركلات والركب',
                'desc_en' => 'Lower-body striking from open and closed stance.',
                'desc_ar' => 'ضربات الجزء السفلي من الوقفة المفتوحة والمغلقة.',
                'exercises' => [
                    ['name_en' => 'Lead Teep (Push Kick)', 'name_ar' => 'الركلة الأمامية الدافعة (التيب)', 'difficulty' => 'beginner', 'equipment' => 'none', 'movement_pattern' => 'push_kick', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Rear Roundhouse Kick', 'name_ar' => 'الركلة الدائرية الخلفية', 'difficulty' => 'intermediate', 'equipment' => 'pads', 'movement_pattern' => 'round_kick', 'sets' => 4, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Low Calf Kick', 'name_ar' => 'الركلة المنخفضة على الساق', 'difficulty' => 'intermediate', 'equipment' => 'pads', 'movement_pattern' => 'round_kick', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Switch Kick', 'name_ar' => 'الركلة بتبديل الوقفة', 'difficulty' => 'advanced', 'equipment' => 'pads', 'movement_pattern' => 'round_kick', 'sets' => 4, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 60],
                    ['name_en' => 'Side Kick', 'name_ar' => 'الركلة الجانبية', 'difficulty' => 'intermediate', 'equipment' => 'pads', 'movement_pattern' => 'side_kick', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 60],
                    ['name_en' => 'Rear Straight Knee', 'name_ar' => 'ضربة الركبة المستقيمة الخلفية', 'difficulty' => 'beginner', 'equipment' => 'pads', 'movement_pattern' => 'knee_strike', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Clinch Knee Strikes', 'name_ar' => 'ضربات الركبة في الاشتباك', 'difficulty' => 'intermediate', 'equipment' => 'heavy bag', 'movement_pattern' => 'knee_strike', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 4, 'round_seconds' => 60, 'round_rest_seconds' => 45],
                    ['name_en' => 'Question Mark Kick', 'name_ar' => 'ركلة علامة الاستفهام', 'difficulty' => 'advanced', 'equipment' => 'pads', 'movement_pattern' => 'round_kick', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 75],
                ],
            ],
            [
                'slug' => 'defense',
                'en' => 'Defense',
                'ar' => 'الدفاع',
                'desc_en' => 'Head movement, blocking and takedown defense.',
                'desc_ar' => 'حركة الرأس والصد والدفاع ضد الإسقاطات.',
                'exercises' => [
                    ['name_en' => 'Slip Left and Right', 'name_ar' => 'التفادي بالميلان يميناً ويساراً', 'difficulty' => 'beginner', 'equipment' => 'none', 'movement_pattern' => 'head_movement', 'sets' => 4, 'reps_min' => 20, 'reps_max' => 30, 'rest_seconds' => 45],
                    ['name_en' => 'Roll Under the Hook', 'name_ar' => 'الدحرجة تحت الخطاف', 'difficulty' => 'intermediate', 'equipment' => 'none', 'movement_pattern' => 'head_movement', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Parry', 'name_ar' => 'الصد بالكف (الباري)', 'difficulty' => 'beginner', 'equipment' => 'gloves', 'movement_pattern' => 'block', 'sets' => 4, 'reps_min' => 20, 'reps_max' => 25, 'rest_seconds' => 45],
                    ['name_en' => 'High Guard Shell', 'name_ar' => 'الحراسة العالية (الدرع)', 'difficulty' => 'beginner', 'equipment' => 'gloves', 'movement_pattern' => 'block', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 45, 'rest_seconds' => 45],
                    ['name_en' => 'Check the Low Kick', 'name_ar' => 'صد الركلة المنخفضة بالساق', 'difficulty' => 'intermediate', 'equipment' => 'none', 'movement_pattern' => 'block', 'sets' => 3, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Sprawl', 'name_ar' => 'السبرول (إجهاض الإسقاط)', 'difficulty' => 'intermediate', 'equipment' => 'mat', 'movement_pattern' => 'takedown_defense', 'sets' => 4, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Lean-Back Counter', 'name_ar' => 'الميلان للخلف مع الهجوم المضاد', 'difficulty' => 'advanced', 'equipment' => 'gloves', 'movement_pattern' => 'counter', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Head Movement Round', 'name_ar' => 'جولة حركة الرأس', 'difficulty' => 'intermediate', 'equipment' => 'none', 'movement_pattern' => 'head_movement', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 3, 'round_seconds' => 120, 'round_rest_seconds' => 60],
                ],
            ],
            [
                'slug' => 'footwork',
                'en' => 'Footwork',
                'ar' => 'حركة القدمين',
                'desc_en' => 'Stance, distance management and angle creation.',
                'desc_ar' => 'الوقفة وإدارة المسافة وخلق الزوايا.',
                'exercises' => [
                    ['name_en' => 'Fighting Stance Hold', 'name_ar' => 'ثبات وقفة القتال', 'difficulty' => 'beginner', 'equipment' => 'none', 'movement_pattern' => 'stance', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 45, 'rest_seconds' => 45],
                    ['name_en' => 'Forward and Backward Step', 'name_ar' => 'الخطوة للأمام والخلف', 'difficulty' => 'beginner', 'equipment' => 'none', 'movement_pattern' => 'footwork', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 4, 'duration_seconds' => 40, 'rest_seconds' => 40],
                    ['name_en' => 'Lateral Shuffle', 'name_ar' => 'التحرك الجانبي', 'difficulty' => 'beginner', 'equipment' => 'none', 'movement_pattern' => 'footwork', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 4, 'duration_seconds' => 40, 'rest_seconds' => 40],
                    ['name_en' => 'Pivot Off the Lead Foot', 'name_ar' => 'الدوران على القدم الأمامية', 'difficulty' => 'intermediate', 'equipment' => 'none', 'movement_pattern' => 'footwork', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Angle Step-Off (L-Step)', 'name_ar' => 'الخروج بزاوية (خطوة L)', 'difficulty' => 'intermediate', 'equipment' => 'none', 'movement_pattern' => 'footwork', 'sets' => 4, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 45],
                    ['name_en' => 'Switch Stance Drill', 'name_ar' => 'تمرين تبديل الوقفة', 'difficulty' => 'advanced', 'equipment' => 'none', 'movement_pattern' => 'footwork', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 4, 'duration_seconds' => 45, 'rest_seconds' => 45],
                    ['name_en' => 'Ladder Quick Feet', 'name_ar' => 'سرعة القدمين على سلم الرشاقة', 'difficulty' => 'beginner', 'equipment' => 'agility ladder', 'movement_pattern' => 'footwork', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 4, 'duration_seconds' => 30, 'rest_seconds' => 45],
                    ['name_en' => 'Cage Cutting Drill', 'name_ar' => 'تمرين قطع مسار الخصم في القفص', 'difficulty' => 'advanced', 'equipment' => 'none', 'movement_pattern' => 'footwork', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 3, 'round_seconds' => 120, 'round_rest_seconds' => 60],
                ],
            ],
            [
                'slug' => 'takedowns',
                'en' => 'Takedowns',
                'ar' => 'الإسقاطات',
                'desc_en' => 'Entries, level changes and finishes for wrestling in MMA.',
                'desc_ar' => 'الدخلات وتغيير المستوى وإنهاء الإسقاط في المصارعة داخل الفنون المختلطة.',
                'exercises' => [
                    ['name_en' => 'Level Change Drill', 'name_ar' => 'تمرين تغيير المستوى', 'difficulty' => 'beginner', 'equipment' => 'mat', 'movement_pattern' => 'level_change', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Penetration Step', 'name_ar' => 'خطوة الاختراق', 'difficulty' => 'beginner', 'equipment' => 'mat', 'movement_pattern' => 'takedown_entry', 'sets' => 4, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 45],
                    ['name_en' => 'Double-Leg Takedown', 'name_ar' => 'الإسقاط المزدوج للساقين', 'difficulty' => 'intermediate', 'equipment' => 'mat', 'movement_pattern' => 'takedown', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 75],
                    ['name_en' => 'Single-Leg Takedown', 'name_ar' => 'الإسقاط الأحادي للساق', 'difficulty' => 'intermediate', 'equipment' => 'mat', 'movement_pattern' => 'takedown', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 75],
                    ['name_en' => 'Body Lock Takedown', 'name_ar' => 'الإسقاط بقفل الجذع', 'difficulty' => 'advanced', 'equipment' => 'mat', 'movement_pattern' => 'takedown', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 90],
                    ['name_en' => 'Hip Toss', 'name_ar' => 'الرمية من الورك', 'difficulty' => 'advanced', 'equipment' => 'mat', 'movement_pattern' => 'throw', 'sets' => 3, 'reps_min' => 6, 'reps_max' => 8, 'rest_seconds' => 90],
                ],
            ],
            [
                'slug' => 'grappling',
                'en' => 'Grappling & Ground',
                'ar' => 'المصارعة الأرضية',
                'desc_en' => 'Positional control, escapes and submission entries.',
                'desc_ar' => 'التحكم بالوضعيات والهروب والدخول إلى الخضوعات.',
                'exercises' => [
                    ['name_en' => 'Shrimping (Hip Escape)', 'name_ar' => 'الهروب بالورك (الشرمب)', 'difficulty' => 'beginner', 'equipment' => 'mat', 'movement_pattern' => 'escape', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 45],
                    ['name_en' => 'Technical Stand-Up', 'name_ar' => 'النهوض التقني', 'difficulty' => 'beginner', 'equipment' => 'mat', 'movement_pattern' => 'escape', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 45],
                    ['name_en' => 'Guard Retention Drill', 'name_ar' => 'تمرين الحفاظ على الحارس', 'difficulty' => 'intermediate', 'equipment' => 'mat', 'movement_pattern' => 'guard', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 4, 'duration_seconds' => 60, 'rest_seconds' => 60],
                    ['name_en' => 'Mount Escape (Bridge and Roll)', 'name_ar' => 'الهروب من الاعتلاء (الجسر والدحرجة)', 'difficulty' => 'intermediate', 'equipment' => 'mat', 'movement_pattern' => 'escape', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 60],
                    ['name_en' => 'Sprawl to Front Headlock', 'name_ar' => 'من السبرول إلى قفل الرأس الأمامي', 'difficulty' => 'intermediate', 'equipment' => 'mat', 'movement_pattern' => 'transition', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 60],
                    ['name_en' => 'Back Take from Turtle', 'name_ar' => 'أخذ الظهر من وضعية السلحفاة', 'difficulty' => 'advanced', 'equipment' => 'mat', 'movement_pattern' => 'transition', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 75],
                    ['name_en' => 'Rear Naked Choke Entry', 'name_ar' => 'الدخول إلى خنق الظهر العاري', 'difficulty' => 'advanced', 'equipment' => 'mat', 'movement_pattern' => 'submission', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 60],
                    ['name_en' => 'Ground and Pound Positional Round', 'name_ar' => 'جولة الضرب من الوضعية العلوية', 'difficulty' => 'advanced', 'equipment' => 'mat', 'movement_pattern' => 'positional', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 3, 'round_seconds' => 120, 'round_rest_seconds' => 60],
                ],
            ],
            [
                'slug' => 'conditioning',
                'en' => 'Conditioning',
                'ar' => 'اللياقة البدنية',
                'desc_en' => 'Round-based aerobic and anaerobic work that matches fight timing.',
                'desc_ar' => 'عمل هوائي ولاهوائي بنظام الجولات يحاكي توقيت النزال.',
                'exercises' => [
                    ['name_en' => 'Jump Rope Rounds', 'name_ar' => 'جولات نط الحبل', 'difficulty' => 'beginner', 'equipment' => 'jump rope', 'movement_pattern' => 'conditioning', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 5, 'round_seconds' => 180, 'round_rest_seconds' => 60],
                    ['name_en' => 'Shadow Sprawl Circuit', 'name_ar' => 'دائرة الظل مع السبرول', 'difficulty' => 'beginner', 'equipment' => 'none', 'movement_pattern' => 'conditioning', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 4, 'round_seconds' => 120, 'round_rest_seconds' => 60],
                    ['name_en' => 'Round-Based Interval Run', 'name_ar' => 'الجري المتقطع بنظام الجولات', 'difficulty' => 'intermediate', 'equipment' => 'none', 'movement_pattern' => 'conditioning', 'metric_type' => 'rounds', 'exercise_type' => 'interval', 'rounds' => 5, 'round_seconds' => 180, 'round_rest_seconds' => 60],
                    ['name_en' => 'Burpee to Sprawl', 'name_ar' => 'بيربي مع سبرول', 'difficulty' => 'intermediate', 'equipment' => 'none', 'movement_pattern' => 'conditioning', 'sets' => 4, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Battle Rope Waves', 'name_ar' => 'موجات حبال القتال', 'difficulty' => 'intermediate', 'equipment' => 'battle ropes', 'home_friendly' => false, 'movement_pattern' => 'conditioning', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 4, 'duration_seconds' => 30, 'rest_seconds' => 60],
                    ['name_en' => 'Assault Bike Sprint Intervals', 'name_ar' => 'سباقات دراجة المقاومة المتقطعة', 'difficulty' => 'advanced', 'equipment' => 'air bike', 'home_friendly' => false, 'movement_pattern' => 'conditioning', 'metric_type' => 'rounds', 'exercise_type' => 'interval', 'rounds' => 8, 'round_seconds' => 20, 'round_rest_seconds' => 40],
                    ['name_en' => 'Heavy Bag Max Output Blast', 'name_ar' => 'انفجار الإخراج الأقصى على الكيس الثقيل', 'difficulty' => 'advanced', 'equipment' => 'heavy bag', 'movement_pattern' => 'conditioning', 'metric_type' => 'rounds', 'exercise_type' => 'interval', 'rounds' => 5, 'round_seconds' => 30, 'round_rest_seconds' => 60],
                ],
            ],
            [
                'slug' => 'power',
                'en' => 'Power & Explosiveness',
                'ar' => 'القوة الانفجارية',
                'desc_en' => 'Ballistic and plyometric work to raise rate of force development.',
                'desc_ar' => 'تمارين تقذفية وبليومترية لرفع معدل إنتاج القوة.',
                'exercises' => [
                    ['name_en' => 'Medicine Ball Rotational Throw', 'name_ar' => 'رمي الكرة الطبية بالدوران', 'difficulty' => 'intermediate', 'equipment' => 'medicine ball', 'movement_pattern' => 'rotation', 'sets' => 4, 'reps_min' => 6, 'reps_max' => 8, 'rest_seconds' => 90],
                    ['name_en' => 'Medicine Ball Slam', 'name_ar' => 'ضرب الكرة الطبية على الأرض', 'difficulty' => 'beginner', 'equipment' => 'medicine ball', 'movement_pattern' => 'overhead_throw', 'sets' => 4, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 75],
                    ['name_en' => 'Box Jump', 'name_ar' => 'القفز على الصندوق', 'difficulty' => 'intermediate', 'equipment' => 'plyo box', 'movement_pattern' => 'jump', 'sets' => 4, 'reps_min' => 5, 'reps_max' => 6, 'rest_seconds' => 90],
                    ['name_en' => 'Broad Jump', 'name_ar' => 'القفز الطويل من الثبات', 'difficulty' => 'intermediate', 'equipment' => 'none', 'movement_pattern' => 'jump', 'sets' => 4, 'reps_min' => 4, 'reps_max' => 6, 'rest_seconds' => 90],
                    ['name_en' => 'Plyometric Push-Up', 'name_ar' => 'تمرين الضغط البليومتري', 'difficulty' => 'advanced', 'equipment' => 'bodyweight', 'movement_pattern' => 'horizontal_push', 'sets' => 4, 'reps_min' => 5, 'reps_max' => 8, 'rest_seconds' => 90],
                    ['name_en' => 'Trap Bar Jump', 'name_ar' => 'القفز ببار الترابيزي', 'difficulty' => 'advanced', 'equipment' => 'trap bar', 'home_friendly' => false, 'movement_pattern' => 'jump', 'sets' => 4, 'reps_min' => 3, 'reps_max' => 5, 'rest_seconds' => 120],
                ],
            ],
        ]);
    }

    private function seedHome(): void
    {
        $defaults = [
            'gym_friendly' => true,
            'home_friendly' => true,
            'bodyweight_supported' => true,
            'mma_specific' => false,
            'equipment' => 'bodyweight',
            'instructions_en' => 'Move through the largest range you can control and pause briefly at the hardest position of each rep.',
            'instructions_ar' => 'تحرك ضمن أوسع مدى يمكنك التحكم به، وتوقف لحظة عند أصعب نقطة في كل تكرار.',
            'safety_notes_en' => 'Reduce the range or switch to an easier variation if a joint complains; never train through sharp pain.',
            'safety_notes_ar' => 'قلّل المدى أو انتقل إلى نسخة أسهل عند الشعور بانزعاج في المفصل، ولا تتدرب أبداً مع ألم حاد.',
        ];

        $this->seedCategories('home_calisthenics', 'home', 500, $defaults, [
            [
                'slug' => 'push',
                'en' => 'Push',
                'ar' => 'تمارين الدفع',
                'desc_en' => 'A full push-up progression from wall support to one-arm work.',
                'desc_ar' => 'تدرج كامل لتمرين الضغط من الإسناد على الحائط حتى الضغط بذراع واحدة.',
                'exercises' => [
                    ['name_en' => 'Wall Push-Up', 'name_ar' => 'الضغط على الحائط', 'difficulty' => 'beginner', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'shoulders'], 'movement_pattern' => 'horizontal_push', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Incline Push-Up', 'name_ar' => 'الضغط المائل على سطح مرتفع', 'difficulty' => 'beginner', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'shoulders'], 'movement_pattern' => 'horizontal_push', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 45],
                    ['name_en' => 'Knee Push-Up', 'name_ar' => 'الضغط على الركبتين', 'difficulty' => 'beginner', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'core'], 'movement_pattern' => 'horizontal_push', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 15, 'rest_seconds' => 45],
                    ['name_en' => 'Standard Push-Up', 'name_ar' => 'الضغط القياسي', 'difficulty' => 'intermediate', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'core'], 'movement_pattern' => 'horizontal_push', 'sets' => 4, 'reps_min' => 10, 'reps_max' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Decline Push-Up', 'name_ar' => 'الضغط المنحدر (القدمان مرتفعتان)', 'difficulty' => 'intermediate', 'primary_muscles' => ['upper chest'], 'secondary_muscles' => ['shoulders', 'triceps'], 'movement_pattern' => 'horizontal_push', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Diamond Push-Up', 'name_ar' => 'ضغط الماس', 'difficulty' => 'advanced', 'primary_muscles' => ['triceps'], 'secondary_muscles' => ['chest'], 'movement_pattern' => 'horizontal_push', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Wide Grip Push-Up', 'name_ar' => 'الضغط بقبضة عريضة', 'difficulty' => 'advanced', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['shoulders', 'triceps'], 'movement_pattern' => 'horizontal_push', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Pike Push-Up', 'name_ar' => 'ضغط البايك', 'difficulty' => 'intermediate', 'primary_muscles' => ['shoulders'], 'secondary_muscles' => ['triceps'], 'movement_pattern' => 'vertical_push', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'Archer Push-Up', 'name_ar' => 'ضغط الرامي', 'difficulty' => 'advanced', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'core'], 'movement_pattern' => 'horizontal_push', 'sets' => 4, 'reps_min' => 5, 'reps_max' => 8, 'rest_seconds' => 75],
                    ['name_en' => 'One-Arm Push-Up', 'name_ar' => 'الضغط بذراع واحدة', 'difficulty' => 'advanced', 'primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'obliques'], 'movement_pattern' => 'horizontal_push', 'sets' => 4, 'reps_min' => 3, 'reps_max' => 6, 'rest_seconds' => 120],
                ],
            ],
            [
                'slug' => 'pull',
                'en' => 'Pull',
                'ar' => 'تمارين السحب',
                'desc_en' => 'Row and pull-up progressions using a bar, table or towel.',
                'desc_ar' => 'تدرجات التجديف والعقلة باستخدام العارضة أو الطاولة أو المنشفة.',
                'exercises' => [
                    ['name_en' => 'Towel Doorway Row', 'name_ar' => 'التجديف على إطار الباب بالمنشفة', 'difficulty' => 'beginner', 'equipment' => 'towel', 'primary_muscles' => ['mid back'], 'secondary_muscles' => ['biceps'], 'movement_pattern' => 'horizontal_pull', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 45],
                    ['name_en' => 'Table Inverted Row', 'name_ar' => 'التجديف المقلوب تحت الطاولة', 'difficulty' => 'beginner', 'equipment' => 'table', 'primary_muscles' => ['mid back'], 'secondary_muscles' => ['biceps', 'core'], 'movement_pattern' => 'horizontal_pull', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Bar Dead Hang', 'name_ar' => 'التعلق الساكن على العارضة', 'difficulty' => 'beginner', 'equipment' => 'pull-up bar', 'primary_muscles' => ['forearms'], 'secondary_muscles' => ['lats', 'shoulders'], 'movement_pattern' => 'hang', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 30, 'rest_seconds' => 60],
                    ['name_en' => 'Scapular Pull-Up', 'name_ar' => 'سحب لوح الكتف على العارضة', 'difficulty' => 'beginner', 'equipment' => 'pull-up bar', 'primary_muscles' => ['lower traps'], 'secondary_muscles' => ['lats'], 'movement_pattern' => 'vertical_pull', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 60],
                    ['name_en' => 'Negative Pull-Up', 'name_ar' => 'العقلة السلبية (النزول البطيء)', 'difficulty' => 'intermediate', 'equipment' => 'pull-up bar', 'primary_muscles' => ['lats'], 'secondary_muscles' => ['biceps'], 'movement_pattern' => 'vertical_pull', 'sets' => 4, 'reps_min' => 4, 'reps_max' => 6, 'rest_seconds' => 90],
                    ['name_en' => 'Band-Assisted Pull-Up', 'name_ar' => 'العقلة بمساعدة المطاط', 'difficulty' => 'intermediate', 'equipment' => 'resistance band', 'primary_muscles' => ['lats'], 'secondary_muscles' => ['biceps'], 'movement_pattern' => 'vertical_pull', 'sets' => 4, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 90],
                    ['name_en' => 'Pull-Up', 'name_ar' => 'العقلة', 'difficulty' => 'advanced', 'equipment' => 'pull-up bar', 'primary_muscles' => ['lats'], 'secondary_muscles' => ['biceps', 'mid back'], 'movement_pattern' => 'vertical_pull', 'sets' => 4, 'reps_min' => 5, 'reps_max' => 10, 'rest_seconds' => 120],
                    ['name_en' => 'Chin-Up', 'name_ar' => 'العقلة بقبضة سفلية', 'difficulty' => 'intermediate', 'equipment' => 'pull-up bar', 'primary_muscles' => ['biceps'], 'secondary_muscles' => ['lats'], 'movement_pattern' => 'vertical_pull', 'sets' => 3, 'reps_min' => 6, 'reps_max' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Archer Pull-Up', 'name_ar' => 'عقلة الرامي', 'difficulty' => 'advanced', 'equipment' => 'pull-up bar', 'primary_muscles' => ['lats'], 'secondary_muscles' => ['biceps', 'core'], 'movement_pattern' => 'vertical_pull', 'sets' => 4, 'reps_min' => 3, 'reps_max' => 6, 'rest_seconds' => 120],
                ],
            ],
            [
                'slug' => 'legs',
                'en' => 'Legs',
                'ar' => 'تمارين الأرجل',
                'desc_en' => 'Bilateral and single-leg lower-body work with no equipment.',
                'desc_ar' => 'تمارين الجزء السفلي على الساقين وعلى ساق واحدة دون معدات.',
                'exercises' => [
                    ['name_en' => 'Bodyweight Squat', 'name_ar' => 'القرفصاء بوزن الجسم', 'difficulty' => 'beginner', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes'], 'movement_pattern' => 'squat', 'sets' => 3, 'reps_min' => 15, 'reps_max' => 25, 'rest_seconds' => 45],
                    ['name_en' => 'Bodyweight Reverse Lunge', 'name_ar' => 'الاندفاع الخلفي بوزن الجسم', 'difficulty' => 'beginner', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes'], 'movement_pattern' => 'lunge', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 60],
                    ['name_en' => 'Chair Bulgarian Split Squat', 'name_ar' => 'القرفصاء البلغاري على الكرسي', 'difficulty' => 'intermediate', 'equipment' => 'chair', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes'], 'movement_pattern' => 'lunge', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'Chair Step-Up', 'name_ar' => 'الصعود على الكرسي', 'difficulty' => 'beginner', 'equipment' => 'chair', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes'], 'movement_pattern' => 'lunge', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Floor Glute Bridge', 'name_ar' => 'جسر الأرداف على الأرض', 'difficulty' => 'beginner', 'primary_muscles' => ['glutes'], 'secondary_muscles' => ['hamstrings'], 'movement_pattern' => 'hip_extension', 'sets' => 3, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Single-Leg Glute Bridge', 'name_ar' => 'جسر الأرداف على ساق واحدة', 'difficulty' => 'intermediate', 'primary_muscles' => ['glutes'], 'secondary_muscles' => ['hamstrings', 'core'], 'movement_pattern' => 'hip_extension', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Wall Sit', 'name_ar' => 'الجلوس على الحائط', 'difficulty' => 'beginner', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes'], 'movement_pattern' => 'squat', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 45, 'rest_seconds' => 60],
                    ['name_en' => 'Nordic Curl Negative', 'name_ar' => 'النزول البطيء في تمرين النورديك', 'difficulty' => 'advanced', 'primary_muscles' => ['hamstrings'], 'secondary_muscles' => ['calves'], 'movement_pattern' => 'knee_flexion', 'sets' => 3, 'reps_min' => 4, 'reps_max' => 6, 'rest_seconds' => 120],
                    ['name_en' => 'Pistol Squat', 'name_ar' => 'قرفصاء المسدس', 'difficulty' => 'advanced', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes', 'core'], 'movement_pattern' => 'squat', 'sets' => 3, 'reps_min' => 3, 'reps_max' => 6, 'rest_seconds' => 90],
                ],
            ],
            [
                'slug' => 'core',
                'en' => 'Core',
                'ar' => 'عضلات الجذع',
                'desc_en' => 'Bracing, flexion and rotation drills you can do on a mat.',
                'desc_ar' => 'تمارين تثبيت وانثناء ودوران يمكن أداؤها على سجادة.',
                'exercises' => [
                    ['name_en' => 'Front Plank', 'name_ar' => 'البلانك الأمامي', 'difficulty' => 'beginner', 'primary_muscles' => ['core'], 'secondary_muscles' => ['shoulders', 'glutes'], 'movement_pattern' => 'anti_extension', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 45, 'rest_seconds' => 45],
                    ['name_en' => 'Side Plank Hold', 'name_ar' => 'ثبات البلانك الجانبي', 'difficulty' => 'beginner', 'primary_muscles' => ['obliques'], 'secondary_muscles' => ['gluteus medius'], 'movement_pattern' => 'anti_lateral_flexion', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 30, 'rest_seconds' => 45],
                    ['name_en' => 'Hollow Body Hold', 'name_ar' => 'وضعية الجسم المجوف', 'difficulty' => 'intermediate', 'primary_muscles' => ['rectus abdominis'], 'secondary_muscles' => ['hip flexors'], 'movement_pattern' => 'anti_extension', 'metric_type' => 'duration', 'exercise_type' => 'isometric', 'sets' => 3, 'duration_seconds' => 30, 'rest_seconds' => 60],
                    ['name_en' => 'Dead Bug March', 'name_ar' => 'مسير الحشرة الميتة', 'difficulty' => 'beginner', 'primary_muscles' => ['core'], 'secondary_muscles' => ['hip flexors'], 'movement_pattern' => 'anti_extension', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 45],
                    ['name_en' => 'Bird Dog', 'name_ar' => 'تمرين الطائر والكلب', 'difficulty' => 'beginner', 'primary_muscles' => ['core'], 'secondary_muscles' => ['glutes', 'lower back'], 'movement_pattern' => 'anti_rotation', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 45],
                    ['name_en' => 'Lying Leg Raise', 'name_ar' => 'رفع الرجلين مستلقياً', 'difficulty' => 'beginner', 'primary_muscles' => ['lower abs'], 'secondary_muscles' => ['hip flexors'], 'movement_pattern' => 'trunk_flexion', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 45],
                    ['name_en' => 'Bicycle Crunch', 'name_ar' => 'الطحن الدراجي', 'difficulty' => 'beginner', 'primary_muscles' => ['obliques'], 'secondary_muscles' => ['rectus abdominis'], 'movement_pattern' => 'rotation', 'sets' => 3, 'reps_min' => 15, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'V-Up', 'name_ar' => 'تمرين الـ V', 'difficulty' => 'intermediate', 'primary_muscles' => ['rectus abdominis'], 'secondary_muscles' => ['hip flexors'], 'movement_pattern' => 'trunk_flexion', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                ],
            ],
            [
                'slug' => 'skills',
                'en' => 'Skills',
                'ar' => 'تمارين المهارات',
                'desc_en' => 'Balance and static-strength skills built through graded holds.',
                'desc_ar' => 'مهارات التوازن والقوة الثابتة تُبنى عبر ثباتات متدرجة.',
                'exercises' => [
                    ['name_en' => 'Crow Pose', 'name_ar' => 'وضعية الغراب', 'difficulty' => 'beginner', 'primary_muscles' => ['shoulders', 'core'], 'secondary_muscles' => ['forearms'], 'movement_pattern' => 'balance', 'exercise_type' => 'skill', 'metric_type' => 'duration', 'sets' => 4, 'duration_seconds' => 20, 'rest_seconds' => 60],
                    ['name_en' => 'Tuck L-Sit', 'name_ar' => 'وضعية L المطوية', 'difficulty' => 'beginner', 'equipment' => 'parallettes', 'primary_muscles' => ['core'], 'secondary_muscles' => ['triceps', 'hip flexors'], 'movement_pattern' => 'static_hold', 'exercise_type' => 'skill', 'metric_type' => 'duration', 'sets' => 4, 'duration_seconds' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'One-Leg Extended L-Sit', 'name_ar' => 'وضعية L بساق ممدودة واحدة', 'difficulty' => 'intermediate', 'equipment' => 'parallettes', 'primary_muscles' => ['core'], 'secondary_muscles' => ['triceps', 'hip flexors'], 'movement_pattern' => 'static_hold', 'exercise_type' => 'skill', 'metric_type' => 'duration', 'sets' => 4, 'duration_seconds' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Full L-Sit', 'name_ar' => 'وضعية L الكاملة', 'difficulty' => 'advanced', 'equipment' => 'parallettes', 'primary_muscles' => ['core'], 'secondary_muscles' => ['triceps', 'quadriceps'], 'movement_pattern' => 'static_hold', 'exercise_type' => 'skill', 'metric_type' => 'duration', 'sets' => 4, 'duration_seconds' => 15, 'rest_seconds' => 90],
                    ['name_en' => 'Wall Handstand Hold', 'name_ar' => 'الوقوف على اليدين بإسناد الحائط', 'difficulty' => 'intermediate', 'primary_muscles' => ['shoulders'], 'secondary_muscles' => ['core', 'triceps'], 'movement_pattern' => 'static_hold', 'exercise_type' => 'skill', 'metric_type' => 'duration', 'sets' => 4, 'duration_seconds' => 30, 'rest_seconds' => 90],
                    ['name_en' => 'Handstand Wall Walk', 'name_ar' => 'المشي على الحائط نحو الوقوف على اليدين', 'difficulty' => 'intermediate', 'primary_muscles' => ['shoulders'], 'secondary_muscles' => ['core'], 'movement_pattern' => 'skill', 'exercise_type' => 'skill', 'sets' => 3, 'reps_min' => 4, 'reps_max' => 6, 'rest_seconds' => 90],
                    ['name_en' => 'Tuck Front Lever', 'name_ar' => 'الرافعة الأمامية المطوية', 'difficulty' => 'advanced', 'equipment' => 'pull-up bar', 'primary_muscles' => ['lats', 'core'], 'secondary_muscles' => ['forearms'], 'movement_pattern' => 'static_hold', 'exercise_type' => 'skill', 'metric_type' => 'duration', 'sets' => 4, 'duration_seconds' => 12, 'rest_seconds' => 90],
                    ['name_en' => 'Muscle-Up Transition Drill', 'name_ar' => 'تمرين انتقال المسل أب', 'difficulty' => 'advanced', 'equipment' => 'pull-up bar', 'primary_muscles' => ['lats', 'triceps'], 'secondary_muscles' => ['chest', 'core'], 'movement_pattern' => 'skill', 'exercise_type' => 'skill', 'sets' => 4, 'reps_min' => 3, 'reps_max' => 5, 'rest_seconds' => 120],
                ],
            ],
            [
                'slug' => 'conditioning',
                'en' => 'Conditioning',
                'ar' => 'اللياقة',
                'desc_en' => 'Short, repeatable bodyweight intervals for work capacity.',
                'desc_ar' => 'فترات قصيرة متكررة بوزن الجسم لبناء القدرة على العمل.',
                'exercises' => [
                    ['name_en' => 'Jumping Jacks', 'name_ar' => 'القفز المتباعد', 'difficulty' => 'beginner', 'primary_muscles' => ['full body'], 'secondary_muscles' => ['calves'], 'movement_pattern' => 'conditioning', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 4, 'duration_seconds' => 40, 'rest_seconds' => 30],
                    ['name_en' => 'High Knees', 'name_ar' => 'رفع الركبتين عالياً', 'difficulty' => 'beginner', 'primary_muscles' => ['hip flexors'], 'secondary_muscles' => ['calves', 'core'], 'movement_pattern' => 'conditioning', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 4, 'duration_seconds' => 30, 'rest_seconds' => 30],
                    ['name_en' => 'Mountain Climber', 'name_ar' => 'متسلق الجبال', 'difficulty' => 'beginner', 'primary_muscles' => ['core'], 'secondary_muscles' => ['shoulders', 'hip flexors'], 'movement_pattern' => 'conditioning', 'metric_type' => 'duration', 'exercise_type' => 'timed', 'sets' => 4, 'duration_seconds' => 30, 'rest_seconds' => 40],
                    ['name_en' => 'Squat Jump', 'name_ar' => 'القفز من وضع القرفصاء', 'difficulty' => 'intermediate', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes', 'calves'], 'movement_pattern' => 'jump', 'sets' => 4, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Home Burpee', 'name_ar' => 'البيربي المنزلي', 'difficulty' => 'intermediate', 'primary_muscles' => ['full body'], 'secondary_muscles' => ['chest', 'quadriceps'], 'movement_pattern' => 'conditioning', 'sets' => 4, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                    ['name_en' => 'Shuttle Run', 'name_ar' => 'الجري المكوكي', 'difficulty' => 'intermediate', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['calves', 'glutes'], 'movement_pattern' => 'conditioning', 'metric_type' => 'rounds', 'exercise_type' => 'interval', 'rounds' => 6, 'round_seconds' => 30, 'round_rest_seconds' => 45],
                    ['name_en' => 'Tabata Bodyweight Circuit', 'name_ar' => 'دائرة تاباتا بوزن الجسم', 'difficulty' => 'advanced', 'primary_muscles' => ['full body'], 'secondary_muscles' => [], 'movement_pattern' => 'conditioning', 'metric_type' => 'rounds', 'exercise_type' => 'interval', 'rounds' => 8, 'round_seconds' => 20, 'round_rest_seconds' => 10],
                ],
            ],
            [
                'slug' => 'mobility',
                'en' => 'Mobility',
                'ar' => 'الإطالة والمرونة',
                'desc_en' => 'Joint-by-joint range work for warm-ups and recovery days.',
                'desc_ar' => 'عمل على مدى حركة المفاصل للإحماء وأيام الاستشفاء.',
                'exercises' => [
                    ['name_en' => 'Cat-Cow', 'name_ar' => 'وضعية القطة والبقرة', 'difficulty' => 'beginner', 'primary_muscles' => ['spine'], 'secondary_muscles' => ['core'], 'movement_pattern' => 'spinal_flow', 'exercise_type' => 'mobility', 'metric_type' => 'duration', 'sets' => 3, 'duration_seconds' => 45, 'rest_seconds' => 30],
                    ['name_en' => 'Deep Lunge with Rotation', 'name_ar' => 'الاندفاع العميق مع دوران الجذع', 'difficulty' => 'beginner', 'primary_muscles' => ['hip flexors'], 'secondary_muscles' => ['thoracic spine', 'hamstrings'], 'movement_pattern' => 'hip_opener', 'exercise_type' => 'mobility', 'metric_type' => 'duration', 'sets' => 3, 'duration_seconds' => 40, 'rest_seconds' => 30],
                    ['name_en' => '90/90 Hip Rotation', 'name_ar' => 'دوران الورك بوضعية 90/90', 'difficulty' => 'beginner', 'primary_muscles' => ['hips'], 'secondary_muscles' => ['glutes'], 'movement_pattern' => 'hip_opener', 'exercise_type' => 'mobility', 'metric_type' => 'duration', 'sets' => 3, 'duration_seconds' => 45, 'rest_seconds' => 30],
                    ['name_en' => 'Thoracic Spine Rotation', 'name_ar' => 'دوران الفقرات الصدرية', 'difficulty' => 'beginner', 'primary_muscles' => ['thoracic spine'], 'secondary_muscles' => ['shoulders'], 'movement_pattern' => 'rotation', 'exercise_type' => 'mobility', 'metric_type' => 'duration', 'sets' => 3, 'duration_seconds' => 40, 'rest_seconds' => 30],
                    ['name_en' => 'Towel Shoulder Dislocate', 'name_ar' => 'تدوير الكتفين بالمنشفة', 'difficulty' => 'beginner', 'equipment' => 'towel', 'primary_muscles' => ['shoulders'], 'secondary_muscles' => ['chest'], 'movement_pattern' => 'shoulder_mobility', 'exercise_type' => 'mobility', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 30],
                    ['name_en' => 'Couch Stretch', 'name_ar' => 'إطالة قابضات الورك على الحائط', 'difficulty' => 'intermediate', 'primary_muscles' => ['hip flexors'], 'secondary_muscles' => ['quadriceps'], 'movement_pattern' => 'hip_opener', 'exercise_type' => 'mobility', 'metric_type' => 'duration', 'sets' => 3, 'duration_seconds' => 45, 'rest_seconds' => 30],
                    ['name_en' => 'Downward Dog to Cobra Flow', 'name_ar' => 'التدفق من الكلب النازل إلى الكوبرا', 'difficulty' => 'beginner', 'primary_muscles' => ['spine'], 'secondary_muscles' => ['hamstrings', 'shoulders'], 'movement_pattern' => 'spinal_flow', 'exercise_type' => 'mobility', 'metric_type' => 'duration', 'sets' => 3, 'duration_seconds' => 45, 'rest_seconds' => 30],
                ],
            ],
            [
                'slug' => 'upper-body',
                'en' => 'Upper Body',
                'ar' => 'الجزء العلوي',
                'desc_en' => 'Mixed push, pull and shoulder-health work for upper-body sessions.',
                'desc_ar' => 'مزيج من الدفع والسحب وتمارين صحة الكتف لحصص الجزء العلوي.',
                'exercises' => [
                    ['name_en' => 'Bodyweight Upper-Body Circuit', 'name_ar' => 'دائرة الجزء العلوي بوزن الجسم', 'difficulty' => 'beginner', 'primary_muscles' => ['chest', 'back'], 'secondary_muscles' => ['shoulders', 'arms'], 'movement_pattern' => 'circuit', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 4, 'round_seconds' => 180, 'round_rest_seconds' => 60],
                    ['name_en' => 'Push-Pull Superset', 'name_ar' => 'سوبرسِت الدفع والسحب', 'difficulty' => 'intermediate', 'primary_muscles' => ['chest', 'back'], 'secondary_muscles' => ['arms'], 'movement_pattern' => 'superset', 'sets' => 4, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 75],
                    ['name_en' => 'Chair Triceps Dip', 'name_ar' => 'غطس الترايسبس على الكرسي', 'difficulty' => 'beginner', 'equipment' => 'chair', 'primary_muscles' => ['triceps'], 'secondary_muscles' => ['shoulders'], 'movement_pattern' => 'vertical_push', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 45],
                    ['name_en' => 'Towel Face Pull', 'name_ar' => 'سحب الوجه بالمنشفة', 'difficulty' => 'beginner', 'equipment' => 'towel', 'primary_muscles' => ['rear deltoids'], 'secondary_muscles' => ['mid back'], 'movement_pattern' => 'horizontal_pull', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest_seconds' => 45],
                    ['name_en' => 'Wall Angel', 'name_ar' => 'ملاك الحائط', 'difficulty' => 'beginner', 'primary_muscles' => ['lower traps'], 'secondary_muscles' => ['rear deltoids'], 'movement_pattern' => 'shoulder_mobility', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 45],
                    ['name_en' => 'Plank Shoulder Tap', 'name_ar' => 'لمس الكتف من وضع البلانك', 'difficulty' => 'beginner', 'primary_muscles' => ['shoulders', 'core'], 'secondary_muscles' => ['chest'], 'movement_pattern' => 'anti_rotation', 'sets' => 3, 'reps_min' => 16, 'reps_max' => 20, 'rest_seconds' => 45],
                ],
            ],
            [
                'slug' => 'lower-body',
                'en' => 'Lower Body',
                'ar' => 'الجزء السفلي',
                'desc_en' => 'Frontal- and sagittal-plane leg work for full lower-body sessions.',
                'desc_ar' => 'تمارين الأرجل في المستويين الأمامي والجانبي لحصص الجزء السفلي الكاملة.',
                'exercises' => [
                    ['name_en' => 'Bodyweight Lower-Body Circuit', 'name_ar' => 'دائرة الجزء السفلي بوزن الجسم', 'difficulty' => 'beginner', 'primary_muscles' => ['quadriceps', 'glutes'], 'secondary_muscles' => ['hamstrings', 'calves'], 'movement_pattern' => 'circuit', 'metric_type' => 'rounds', 'exercise_type' => 'rounds', 'rounds' => 4, 'round_seconds' => 180, 'round_rest_seconds' => 60],
                    ['name_en' => 'Squat to Lunge Complex', 'name_ar' => 'مركب القرفصاء والاندفاع', 'difficulty' => 'intermediate', 'primary_muscles' => ['quadriceps'], 'secondary_muscles' => ['glutes', 'core'], 'movement_pattern' => 'complex', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 75],
                    ['name_en' => 'Lateral Lunge', 'name_ar' => 'الاندفاع الجانبي', 'difficulty' => 'beginner', 'primary_muscles' => ['adductors'], 'secondary_muscles' => ['quadriceps', 'glutes'], 'movement_pattern' => 'lateral_lunge', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 60],
                    ['name_en' => 'Curtsy Lunge', 'name_ar' => 'اندفاع الانحناء المتقاطع', 'difficulty' => 'beginner', 'primary_muscles' => ['gluteus medius'], 'secondary_muscles' => ['quadriceps'], 'movement_pattern' => 'lunge', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 60],
                    ['name_en' => 'Single-Leg Calf Raise', 'name_ar' => 'رفع السمانة على ساق واحدة', 'difficulty' => 'beginner', 'primary_muscles' => ['calves'], 'secondary_muscles' => ['ankle stabilisers'], 'movement_pattern' => 'plantar_flexion', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 20, 'rest_seconds' => 45],
                    ['name_en' => 'Cossack Squat', 'name_ar' => 'قرفصاء القوزاق', 'difficulty' => 'intermediate', 'primary_muscles' => ['adductors'], 'secondary_muscles' => ['quadriceps', 'glutes'], 'movement_pattern' => 'lateral_lunge', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 10, 'rest_seconds' => 60],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  list<array<string, mixed>>  $groups
     */
    private function seedCategories(string $type, string $slugPrefix, int $sortStart, array $defaults, array $groups): void
    {
        $sort = $sortStart;

        foreach ($groups as $group) {
            $slug = $slugPrefix.'-'.$group['slug'];

            $category = $this->upsertCategory(
                slug: $slug,
                nameEn: $group['en'],
                nameAr: $group['ar'],
                type: $type,
                sort: $sort,
                descEn: $group['desc_en'],
                descAr: $group['desc_ar'],
            );

            foreach (array_values($group['exercises']) as $index => $exercise) {
                $this->ids[$slug][$exercise['name_en']] = $this->upsertExercise(
                    $category,
                    array_merge($defaults, $exercise),
                    $index + 1,
                );
            }

            $sort++;
        }
    }

    private function upsertCategory(
        string $slug,
        string $nameEn,
        string $nameAr,
        string $type,
        int $sort,
        string $descEn,
        string $descAr,
    ): TrainingCategory {
        return TrainingCategory::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'description_en' => $descEn,
                'description_ar' => $descAr,
                'type' => $type,
                // Categories are level-agnostic; the level lives on each exercise.
                'level' => 'all',
                'is_active' => true,
                'sort_order' => $sort,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertExercise(TrainingCategory $category, array $data, int $order): int
    {
        $primary = $data['primary_muscles'] ?? [];
        $secondary = $data['secondary_muscles'] ?? [];

        $exercise = Exercise::query()->updateOrCreate(
            [
                'training_category_id' => $category->id,
                'name_en' => $data['name_en'],
            ],
            [
                'name_ar' => $data['name_ar'],
                'slug' => Str::slug($category->slug.'-'.$data['name_en']),
                'description_en' => $data['description_en'] ?? $this->describeEn($data, $category),
                'description_ar' => $data['description_ar'] ?? $this->describeAr($data, $category),
                'difficulty' => $data['difficulty'] ?? 'beginner',
                'instructions_en' => $data['instructions_en'] ?? null,
                'instructions_ar' => $data['instructions_ar'] ?? null,
                'safety_notes_en' => $data['safety_notes_en'] ?? null,
                'safety_notes_ar' => $data['safety_notes_ar'] ?? null,
                'thumbnail_url' => null,
                'target' => $data['target'] ?? ($primary !== [] ? implode(', ', $primary) : $category->name_en),
                'equipment' => $data['equipment'] ?? 'bodyweight',
                'bodyweight_supported' => $data['bodyweight_supported'] ?? false,
                'home_friendly' => $data['home_friendly'] ?? false,
                'gym_friendly' => $data['gym_friendly'] ?? true,
                'mma_specific' => $data['mma_specific'] ?? false,
                'equipment_alternatives' => $data['equipment_alternatives'] ?? null,
                'primary_muscles' => $primary,
                'secondary_muscles' => $secondary,
                'metric_type' => $data['metric_type'] ?? 'reps',
                'exercise_type' => $data['exercise_type'] ?? 'sets_reps',
                'movement_pattern' => $data['movement_pattern'] ?? null,
                'sets' => $data['sets'] ?? null,
                'reps_min' => $data['reps_min'] ?? null,
                'reps_max' => $data['reps_max'] ?? null,
                'duration_seconds' => $data['duration_seconds'] ?? null,
                'rest_seconds' => $data['rest_seconds'] ?? null,
                'rounds' => $data['rounds'] ?? null,
                'round_seconds' => $data['round_seconds'] ?? null,
                'round_rest_seconds' => $data['round_rest_seconds'] ?? null,
                'recommended_rir' => $data['recommended_rir'] ?? null,
                'recommended_intensity' => $data['recommended_intensity'] ?? null,
                'tempo' => $data['tempo'] ?? null,
                'evidence_level' => 'informed',
                'evidence_notes' => $data['evidence_notes'] ?? self::EVIDENCE_NOTES,
                'source_title' => self::SOURCE_TITLE,
                'source_year' => self::SOURCE_YEAR,
                'is_active' => true,
                'is_demo' => true,
                'sort_order' => $order,
            ],
        );

        $this->attachPlaceholderVideo($exercise);

        return $exercise->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function describeEn(array $data, TrainingCategory $category): string
    {
        return sprintf(
            '%s — %s option for %s, programmed here at %s level.',
            $data['name_en'],
            $data['equipment'] ?? 'bodyweight',
            strtolower($category->name_en),
            $data['difficulty'] ?? 'beginner',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function describeAr(array $data, TrainingCategory $category): string
    {
        return sprintf('%s — خيار تدريبي ضمن %s بمستوى %s.', $data['name_ar'], $category->name_ar, $this->levelAr($data['difficulty'] ?? 'beginner'));
    }

    private function levelAr(string $difficulty): string
    {
        return match ($difficulty) {
            'advanced' => 'متقدم',
            'intermediate' => 'متوسط',
            default => 'مبتدئ',
        };
    }

    private function seedProgressions(): void
    {
        $this->linkProgression('home-push', [
            'Wall Push-Up',
            'Incline Push-Up',
            'Knee Push-Up',
            'Standard Push-Up',
            'Decline Push-Up',
            'Wide Grip Push-Up',
            'Diamond Push-Up',
            'Archer Push-Up',
            'One-Arm Push-Up',
        ]);

        $this->linkProgression('home-pull', [
            'Bar Dead Hang',
            'Scapular Pull-Up',
            'Negative Pull-Up',
            'Band-Assisted Pull-Up',
            'Pull-Up',
            'Archer Pull-Up',
        ]);

        $this->linkProgression('home-skills', [
            'Tuck L-Sit',
            'One-Leg Extended L-Sit',
            'Full L-Sit',
        ]);
    }

    /**
     * Chains exercises easiest-to-hardest so each one points at its neighbours.
     *
     * @param  list<string>  $names
     */
    private function linkProgression(string $categorySlug, array $names): void
    {
        $ids = [];
        foreach ($names as $name) {
            if (isset($this->ids[$categorySlug][$name])) {
                $ids[] = $this->ids[$categorySlug][$name];
            }
        }

        foreach ($ids as $i => $id) {
            Exercise::query()->whereKey($id)->update([
                'regression_exercise_id' => $ids[$i - 1] ?? null,
                'progression_exercise_id' => $ids[$i + 1] ?? null,
            ]);
        }
    }

    private function seedAlternatives(): void
    {
        $this->linkAlternatives('bb-chest', [
            'Barbell Bench Press' => ['Flat Dumbbell Press', 'Machine Chest Press'],
            'Incline Barbell Bench Press' => ['Incline Dumbbell Press', 'Machine Chest Press'],
            'Incline Dumbbell Press' => ['Incline Barbell Bench Press', 'Push-Up'],
        ]);
    }

    /**
     * @param  array<string, list<string>>  $map  exercise name => sibling names
     */
    private function linkAlternatives(string $categorySlug, array $map): void
    {
        foreach ($map as $name => $alternatives) {
            $exerciseId = $this->ids[$categorySlug][$name] ?? null;
            if ($exerciseId === null) {
                continue;
            }

            DB::table('exercise_alternatives')->where('exercise_id', $exerciseId)->delete();

            $rows = [];
            foreach (array_values($alternatives) as $i => $alternative) {
                $alternativeId = $this->ids[$categorySlug][$alternative] ?? null;
                if ($alternativeId === null || $alternativeId === $exerciseId) {
                    continue;
                }

                $rows[] = [
                    'exercise_id' => $exerciseId,
                    'alternative_exercise_id' => $alternativeId,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                DB::table('exercise_alternatives')->insertOrIgnore($rows);
            }
        }
    }

    private function seedPrograms(): void
    {
        $this->upsertProgram(
            slug: 'advanced-chest-program',
            nameEn: 'Advanced Chest',
            nameAr: 'صدر متقدم',
            type: 'bodybuilding',
            level: 'advanced',
            categorySlug: 'bb-chest',
            descEn: 'Heavy compound pressing followed by dip and fly accessory volume.',
            descAr: 'دفع مركب ثقيل يتبعه حجم إضافي من الغطس والتفتيح.',
            items: [
                ['name' => 'Barbell Bench Press', 'sets' => 4, 'reps_min' => 5, 'reps_max' => 8, 'rest' => 150],
                ['name' => 'Incline Barbell Bench Press', 'sets' => 4, 'reps_min' => 6, 'reps_max' => 8, 'rest' => 120],
                ['name' => 'Weighted Chest Dip', 'sets' => 4, 'reps_min' => 6, 'reps_max' => 10, 'rest' => 120],
                ['name' => 'Incline Dumbbell Press', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest' => 90],
                ['name' => 'Cable Chest Fly', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 15, 'rest' => 60],
            ],
        );

        $this->upsertProgram(
            slug: 'mma-striking-fundamentals',
            nameEn: 'MMA Striking Fundamentals',
            nameAr: 'أساسيات الضربات في الفنون المختلطة',
            type: 'mma',
            level: 'intermediate',
            categorySlug: 'mma-striking',
            descEn: 'Single strikes first, then combinations, finishing with shadow rounds.',
            descAr: 'ضربات مفردة أولاً، ثم التركيبات، وينتهي بجولات ملاكمة الظل.',
            items: [
                ['name' => 'Jab', 'sets' => 4, 'reps_min' => 20, 'reps_max' => 30, 'rest' => 45],
                ['name' => 'Cross', 'sets' => 4, 'reps_min' => 20, 'reps_max' => 30, 'rest' => 45],
                ['name' => 'Lead Hook', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest' => 45],
                ['name' => 'Rear Uppercut', 'sets' => 4, 'reps_min' => 15, 'reps_max' => 20, 'rest' => 45],
                ['name' => 'Jab-Cross-Lead Hook (1-2-3) Combination', 'rounds' => 5, 'round_seconds' => 120, 'round_rest_seconds' => 60],
                ['name' => 'Shadow Boxing Round', 'rounds' => 3, 'round_seconds' => 180, 'round_rest_seconds' => 60],
            ],
        );

        $this->upsertProgram(
            slug: 'beginner-home-push',
            nameEn: 'Beginner Home Push',
            nameAr: 'دفع منزلي للمبتدئين',
            type: 'home_calisthenics',
            level: 'beginner',
            categorySlug: 'home-push',
            descEn: 'Builds toward the first clean standard push-up with no equipment.',
            descAr: 'يبني الطريق نحو أول تمرين ضغط قياسي نظيف دون أي معدات.',
            items: [
                ['name' => 'Wall Push-Up', 'sets' => 3, 'reps_min' => 12, 'reps_max' => 20, 'rest' => 45],
                ['name' => 'Incline Push-Up', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest' => 45],
                ['name' => 'Knee Push-Up', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 15, 'rest' => 45],
                ['name' => 'Standard Push-Up', 'sets' => 3, 'reps_min' => 5, 'reps_max' => 10, 'rest' => 60],
                ['name' => 'Front Plank', 'category_slug' => 'home-core', 'sets' => 3, 'duration' => 30, 'rest' => 45],
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function upsertProgram(
        string $slug,
        string $nameEn,
        string $nameAr,
        string $type,
        string $level,
        string $categorySlug,
        string $descEn,
        string $descAr,
        array $items,
    ): void {
        $category = TrainingCategory::query()->where('slug', $categorySlug)->first();
        if ($category === null) {
            return;
        }

        $program = TrainingProgram::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'type' => $type,
                'level' => $level,
                'training_category_id' => $category->id,
                'description_en' => $descEn,
                'description_ar' => $descAr,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        TrainingProgramExercise::query()->where('training_program_id', $program->id)->delete();

        $order = 1;
        foreach ($items as $item) {
            $lookupSlug = $item['category_slug'] ?? $categorySlug;
            $exerciseId = $this->ids[$lookupSlug][$item['name']] ?? null;
            if ($exerciseId === null) {
                continue;
            }

            TrainingProgramExercise::query()->create([
                'training_program_id' => $program->id,
                'exercise_id' => $exerciseId,
                'sort_order' => $order++,
                'target_sets' => $item['sets'] ?? null,
                'target_reps_min' => $item['reps_min'] ?? null,
                'target_reps_max' => $item['reps_max'] ?? null,
                'target_duration_seconds' => $item['duration'] ?? null,
                'rest_seconds' => $item['rest'] ?? null,
                'rounds' => $item['rounds'] ?? null,
                'round_seconds' => $item['round_seconds'] ?? null,
                'round_rest_seconds' => $item['round_rest_seconds'] ?? null,
            ]);
        }
    }

    private function attachPlaceholderVideo(Exercise $exercise): void
    {
        $existing = ExerciseVideo::query()
            ->where('exercise_id', $exercise->id)
            ->where('sort_order', 1)
            ->first();

        // Preserve user-provided graduation demo media under public/exercises.
        if (
            $existing !== null
            && ! $existing->is_placeholder
            && (
                str_contains($existing->video_url, '/exercises/bodybuilding/')
                || str_contains($existing->video_url, '/exercises/mma/')
                || str_contains($existing->video_url, '/exercises/home/')
                || str_contains($existing->video_url, '/storage/exercises/')
            )
        ) {
            return;
        }

        $asset = BodybuildingExerciseMedia::assetForSlug($exercise->slug);
        if ($asset !== null) {
            ExerciseVideo::query()->updateOrCreate(
                [
                    'exercise_id' => $exercise->id,
                    'sort_order' => 1,
                ],
                [
                    'title_en' => $exercise->name_en,
                    'title_ar' => $exercise->name_ar,
                    'video_url' => $asset,
                    'media_type' => 'gif',
                    'thumbnail_url' => null,
                    'duration_seconds' => 2,
                    'is_placeholder' => false,
                    'is_active' => true,
                ],
            );

            return;
        }

        ExerciseVideo::query()->updateOrCreate(
            [
                'exercise_id' => $exercise->id,
                'sort_order' => 1,
            ],
            [
                'title_en' => $exercise->name_en.' — Demo Placeholder',
                'title_ar' => $exercise->name_ar.' — فيديو توضيحي',
                'video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'thumbnail_url' => null,
                'duration_seconds' => 15,
                'is_placeholder' => true,
                'is_active' => true,
            ],
        );
    }
}
