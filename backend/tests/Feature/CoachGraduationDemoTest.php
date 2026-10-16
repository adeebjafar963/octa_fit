<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Coach\Demo\CoachDemoScenarioResolver;
use App\Services\Coach\Demo\CoachDemoScenarioService;
use Database\Seeders\NutritionFoodSeeder;
use Database\Seeders\TrainingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachGraduationDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ai.coach_demo_mode' => true]);
        Role::query()->insert([
            ['id' => 1, 'name' => 'admin', 'display_name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'user', 'display_name' => 'User', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->seed(TrainingSeeder::class);
        $this->seed(NutritionFoodSeeder::class);
    }

    private function user(): User
    {
        $user = User::query()->create([
            'name' => 'Demo',
            'email' => 'demo'.uniqid().'@test.com',
            'password' => Hash::make('password123'),
            'role_id' => 2,
            'is_active' => true,
        ]);
        $user->profile()->create([
            'locale' => 'ar',
            'weight_kg' => 70,
            'height_cm' => 175,
            'age' => 23,
            'training_goal' => 'muscle_gain',
            'profile_completed_at' => now(),
        ]);

        return $user->fresh(['profile']);
    }

    /**
     * @return array<string, mixed>
     */
    private function ask(User $user, string $message, ?int $conversationId = null): array
    {
        return app(CoachDemoScenarioService::class)->tryHandle($user, $message, $conversationId, 'ar') ?? [];
    }

    public function test_demo_mode_off_returns_null(): void
    {
        config(['services.ai.coach_demo_mode' => false]);
        $r = app(CoachDemoScenarioService::class)->tryHandle(
            $this->user(),
            'بدي برنامج تضخيم 5 أيام بالجيم.',
            1,
            'ar',
        );
        $this->assertNull($r);
    }

    public function test_unrelated_question_does_not_match(): void
    {
        $resolver = app(CoachDemoScenarioResolver::class);
        // Sleep alone is Q22; this different sleep phrasing must not false-positive other scenarios.
        $this->assertNull($resolver->resolve('كيف أختار حذاء رياضي للمشي؟'));
        $this->assertNull($resolver->resolve('What is the weather in Damascus today?'));
        $this->assertNull(app(CoachDemoScenarioService::class)->tryHandle(
            $this->user(),
            'كيف أختار حذاء رياضي للمشي؟',
            1,
            'ar',
        ));
    }

    public function test_punctuation_and_spacing_variants_resolve(): void
    {
        $resolver = app(CoachDemoScenarioResolver::class);
        $this->assertSame(4, $resolver->resolve('شو أحسن تمارين للكتف للمبتدئ؟'));
        $this->assertSame(4, $resolver->resolve('شو أحسن تمارين للكتف للمبتدئ'));
        $this->assertSame(4, $resolver->resolve('شو احسن تمارين للكتف للمبتدئ؟'));
        $this->assertSame(4, $resolver->resolve('  شو أحسن   تمارين للكتف للمبتدئ ؟  '));
        $this->assertSame(3, $resolver->resolve('بدي برنامج تضخيم 5 أيام بالجيم'));
        $this->assertSame(5, $resolver->resolve('طيب بدي برنامج أكتاف للمبتدئ'));
        $this->assertSame(23, $resolver->resolve('طيب رجعنا للتمرين، شو أحسن تمارين للظهر للمبتدئ'));
        $this->assertSame(22, $resolver->resolve('شو فوائد النوم والاستشفاء للعضلات'));
    }

    public function test_all_25_scenarios_pass(): void
    {
        $user = $this->user();
        $cid = 920001;
        $canonical = app(CoachDemoScenarioResolver::class)->canonicalQuestions();
        $this->assertCount(25, $canonical);

        foreach ($canonical as $id => $question) {
            $r = $this->ask($user, $question, $cid);
            $this->assertNotEmpty($r, "scenario {$id} empty");
            $this->assertNotSame('', trim((string) ($r['message'] ?? '')), "scenario {$id} empty message");

            match ($id) {
                1 => $this->assertTrue(($r['training_plan'] ?? null) === null && str_contains($r['message'], '70')),
                2 => $this->assertMatchesRegularExpression('/سعرات|بروتين|kcal|تقدير/u', $r['message']),
                3 => $this->assertScenarioHypertrophy($r),
                4 => $this->assertShoulderExercises($r),
                5 => $this->assertShoulderProgram($r),
                6 => $this->assertSame('mma', $r['training_plan']['discipline'] ?? null),
                7 => $this->assertSame('home', $r['training_plan']['equipment_mode'] ?? null),
                8 => $this->assertMatchesRegularExpression('/جيم|أوزان|أجهزة/u', $r['message']),
                9 => $this->assertTrue(
                    is_array($r['training_plan'] ?? null)
                    && (int) ($r['training_plan']['days_per_week'] ?? 0) === 1
                ),
                10 => $this->assertMatchesRegularExpression('/اليوم الثالث|Day 3/u', $r['message']),
                11 => $this->assertMatchesRegularExpression('/اليوم الثاني|Day 2/u', $r['message']),
                12 => $this->assertNotNull($r['training_plan']),
                13 => $this->assertNotEmpty($r['grounded_exercises'] ?? []),
                14, 15 => $this->assertTrue(($r['training_plan'] ?? null) === null),
                16 => $this->assertMatchesRegularExpression('/تونة|بيض|زبادي|لحم|سمك/u', $r['message']),
                17 => $this->assertMatchesRegularExpression('/بروتين|غ/u', $r['message']),
                18 => $this->assertTrue(($r['nutrition_plan'] !== null) || str_contains($r['message'], 'تنشيف')),
                19 => $this->assertMatchesRegularExpression('/بيض|زبادي|تونة|دجاج|بديل/u', $r['message']),
                20 => $this->assertMatchesRegularExpression('/بروتين|سعرات|كالوري|رز|دجاج/u', $r['message']),
                21 => $this->assertMatchesRegularExpression('/بروتين|كربوهيدرات|دهون/u', $r['message']),
                22 => $this->assertMatchesRegularExpression('/نوم|استشفاء|عضلات/u', $r['message']),
                23 => $this->assertBackExercises($r),
                24 => $this->assertDoesNotMatchRegularExpression('/API_KEY|system prompt|OPENAI|env\(/i', $r['message']),
                25 => $this->assertMatchesRegularExpression('/تدريب|أكل|بروتين|تضخيم/u', $r['message']),
                default => null,
            };
        }
    }

    public function test_follow_up_chain_modifies_hypertrophy_plan(): void
    {
        $user = $this->user();
        $cid = 920002;

        $r3 = $this->ask($user, 'بدي برنامج تضخيم 5 أيام بالجيم.', $cid);
        $this->assertSame(5, (int) ($r3['training_plan']['days_per_week'] ?? 0));
        $day3Before = collect($r3['training_plan']['sessions'] ?? [])->firstWhere('day', 3);
        $setsBefore = (int) (($day3Before['exercises'][0]['sets'] ?? 0));

        // Interleaved plans must NOT wipe follow-up hypertrophy context.
        $this->ask($user, 'بدي برنامج MMA لمدة 4 أيام، كل يوم حوالي ساعتين.', $cid);
        $this->ask($user, 'أنا بتمرن بالبيت وما عندي معدات، اعمللي برنامج مناسب.', $cid);
        $this->ask($user, 'شو فيني أعمل اليوم لتمرين كامل للجسم إذا معي ساعة؟', $cid);

        $r10 = $this->ask($user, 'خلي اليوم الثالث أصعب.', $cid);
        $day3After = collect($r10['training_plan']['sessions'] ?? [])->firstWhere('day', 3);
        $this->assertNotNull($day3After);
        $this->assertGreaterThan($setsBefore, (int) ($day3After['exercises'][0]['sets'] ?? 0));
        $this->assertSame(5, (int) ($r10['training_plan']['days_per_week'] ?? 0));

        $r11 = $this->ask($user, 'بدل آخر تمرين باليوم الثاني.', $cid);
        $day2 = collect($r11['training_plan']['sessions'] ?? [])->firstWhere('day', 2);
        $this->assertNotNull($day2);

        $r12 = $this->ask($user, 'خفف حجم التمرين شوي.', $cid);
        $this->assertNotNull($r12['training_plan']);

        $r13 = $this->ask($user, 'طيب إذا ما قدرت أعمل هالتمرين، شو البديل؟', $cid);
        $this->assertNotEmpty($r13['grounded_exercises'] ?? []);
        $this->assertTrue(($r13['training_plan']['days_per_week'] ?? 5) >= 1);
    }

    public function test_topic_switch_back_not_sleep(): void
    {
        $user = $this->user();
        $cid = 920003;
        $this->ask($user, 'شو فوائد النوم والاستشفاء للعضلات؟', $cid);
        $r = $this->ask($user, 'طيب رجعنا للتمرين، شو أحسن تمارين للظهر للمبتدئ؟', $cid);
        $this->assertBackExercises($r);
        $this->assertStringNotContainsString('ساعات النوم', $r['message']);
    }

    public function test_nutrition_calc_and_combined(): void
    {
        $user = $this->user();
        $r20 = $this->ask($user, 'إذا أكلت 200 غرام صدر دجاج و100 غرام رز، تقريباً قديش بروتين وكالوري أخدت؟', 920004);
        $this->assertMatchesRegularExpression('/\d+/', $r20['message']);
        $r25 = $this->ask($user, 'أنا بدي أضخم، شو أفضل برنامج تدريب وأكل مناسب إلي؟', 920004);
        $this->assertMatchesRegularExpression('/تدريب/u', $r25['message']);
        $this->assertMatchesRegularExpression('/أكل|تغذية|بروتين|سعرات/u', $r25['message']);
    }

    public function test_http_api_demo_shoulder_scope(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations', [])->assertCreated()->json('data.id');

        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'شو أحسن تمارين للكتف للمبتدئ؟',
            'language' => 'ar',
        ])->assertCreated();

        $content = (string) $response->json('data.assistant_message.content');
        $this->assertStringContainsString('كتف', $content);
        $this->assertStringNotContainsString('Barbell Back Squat', $content);
        $this->assertNotEmpty($response->json('data.grounded_exercises'));
    }

    public function test_no_medical_demo_scenarios_exist(): void
    {
        $canonical = app(CoachDemoScenarioResolver::class)->canonicalQuestions();
        $blob = implode(' ', $canonical);
        $this->assertDoesNotMatchRegularExpression('/ألم|إصابة|تشخيص|ركبة|علاج طبيعي|physiotherapy|injury|pain/iu', $blob);
        $this->assertCount(25, $canonical);
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function assertScenarioHypertrophy(array $r): void
    {
        $this->assertNotNull($r['training_plan']);
        $this->assertSame(5, (int) ($r['training_plan']['days_per_week'] ?? 0));
        $this->assertSame('gym', $r['training_plan']['equipment_mode'] ?? null);
        $this->assertNotEmpty($r['grounded_exercises']);
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function assertShoulderExercises(array $r): void
    {
        $this->assertNull($r['training_plan'] ?? null);
        $this->assertGreaterThanOrEqual(4, count($r['grounded_exercises'] ?? []));
        foreach ($r['grounded_exercises'] as $ex) {
            $muscles = mb_strtolower(json_encode($ex['primary_muscles'] ?? [], JSON_UNESCAPED_UNICODE));
            $this->assertTrue(str_contains($muscles, 'shoulder') || str_contains($muscles, 'deltoid'));
        }
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function assertShoulderProgram(array $r): void
    {
        $this->assertNotNull($r['training_plan']);
        $this->assertSame('shoulders', $r['training_plan']['body_part'] ?? null);
        $this->assertSame('body_part', $r['training_plan']['scope'] ?? null);
        $this->assertSame(1, (int) ($r['training_plan']['days_per_week'] ?? 0));
        foreach ($r['grounded_exercises'] as $ex) {
            $muscles = mb_strtolower(json_encode($ex['primary_muscles'] ?? [], JSON_UNESCAPED_UNICODE));
            $this->assertTrue(str_contains($muscles, 'shoulder') || str_contains($muscles, 'deltoid'));
        }
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function assertBackExercises(array $r): void
    {
        $this->assertNull($r['training_plan'] ?? null);
        $this->assertGreaterThanOrEqual(4, count($r['grounded_exercises'] ?? []));
        foreach ($r['grounded_exercises'] as $ex) {
            $muscles = mb_strtolower(json_encode($ex['primary_muscles'] ?? [], JSON_UNESCAPED_UNICODE));
            $name = mb_strtolower((string) ($ex['name_en'] ?? $ex['name'] ?? ''));
            $ok = str_contains($muscles, 'back')
                || str_contains($muscles, 'lat')
                || str_contains($name, 'row')
                || str_contains($name, 'pull');
            $this->assertTrue($ok, 'non-back exercise: '.($ex['name'] ?? ''));
            $this->assertFalse(str_contains($muscles, 'quadriceps'));
            $this->assertFalse(str_contains($muscles, 'side deltoid'));
        }
        $this->assertStringNotContainsString('فوائد النوم', $r['message']);
    }
}
