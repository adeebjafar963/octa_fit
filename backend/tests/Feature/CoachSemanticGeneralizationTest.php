<?php

namespace Tests\Feature;

use App\Services\Coach\CoachFollowUpInterpreter;
use App\Services\Coach\Semantic\LocalSemanticInterpreter;
use App\Services\Coach\Semantic\SemanticIntentResolver;
use App\Services\Coach\TrainingPlanEngine;
use Database\Seeders\TrainingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Semantic paraphrase groups — must pass via concept understanding, not exact phrase rules.
 */
class CoachSemanticGeneralizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TrainingSeeder::class);
    }

    /** @return list<string> */
    private function groupA(): array
    {
        return [
            'خلي اليوم الثالث أصعب',
            'زيد صعوبة اليوم الثالث',
            'الحصة التالتة بدي ياها أقوى',
            'ارفع مستوى اليوم 3',
            'خلينا نتحدى حالنا أكثر باليوم الثالث',
            'make day 3 harder',
            'make the third session more challenging',
            'خلينا نخلي الحصة التالتة أقوى شوي',
            'شو رأيك نرفع مستوى اليوم الثالث؟',
            'بدي نخلي الحصة التالتة نار شوي',
            'ارفعلي تحدي اليوم الرابع',
        ];
    }

    /** @return list<string> */
    private function groupB(): array
    {
        return [
            'التفاحة كم سعرة؟',
            'قديش كالوري بالتفاحة؟',
            'كم طاقة فيها تفاحة؟',
            'how many calories are in an apple?',
            'calories in one apple?',
            'قديش كالوري تقريباً بتعطي تفاحة متوسطة؟',
        ];
    }

    public function test_group_a_increase_difficulty_paraphrases(): void
    {
        $interpreter = app(CoachFollowUpInterpreter::class);
        $engine = app(TrainingPlanEngine::class);
        $plan = $engine->generateFromMessage(
            'Give me a 4-day MMA training plan, 2 hours every day.',
            $this->minimalContext(),
            'en',
        );
        $this->assertNotNull($plan['plan'], json_encode($plan['errors'] ?? []));
        $active = ['plan' => $plan['plan'], 'intent' => $plan['intent']];

        foreach ($this->groupA() as $phrase) {
            $local = app(LocalSemanticInterpreter::class)->interpret($phrase, true);
            if (($local['operation']['modification'] ?? '') === 'increase_difficulty') {
                $day = (int) ($local['operation']['target']['index'] ?? $local['entities']['day'] ?? 0);
                $this->assertGreaterThanOrEqual(3, $day, "Failed day for: {$phrase}");
                $this->assertLessThanOrEqual(4, $day, "Failed day for: {$phrase}");
            }

            $follow = $interpreter->interpret($phrase, $active);
            if (str_contains($phrase, 'الرابع') || str_contains($phrase, 'fourth')) {
                $this->assertSame('intensify_day', $follow['action'], $phrase);
                $this->assertSame(4, $follow['day'], $phrase);
            } else {
                $this->assertSame('intensify_day', $follow['action'], $phrase);
                $this->assertSame(3, $follow['day'], $phrase);
            }
        }
    }

    public function test_group_b_nutrition_lookup_paraphrases(): void
    {
        $local = app(LocalSemanticInterpreter::class);
        foreach ($this->groupB() as $phrase) {
            $result = $local->interpret($phrase, false);
            $this->assertSame('nutrition_lookup', $result['intent'], $phrase);
            $this->assertSame('nutrition', $result['domain'], $phrase);
        }
    }

    public function test_group_c_gym_paraphrases(): void
    {
        $local = app(LocalSemanticInterpreter::class);
        $phrases = [
            'بدي برنامج للجيم',
            'اعمللي برنامج بالنادي',
            'بدي gym workout',
            'أنا رايح عالنادي، شو برنامج مناسب إلي؟',
        ];
        foreach ($phrases as $phrase) {
            $result = $local->interpret($phrase, false);
            $this->assertSame('training_plan_create', $result['intent'], $phrase);
            $equip = $result['constraints']['equipment_mode'] ?? $result['entities']['equipment_mode'] ?? null;
            $this->assertSame('gym', $equip, $phrase);
        }
    }

    public function test_group_d_mma_paraphrases(): void
    {
        $local = app(LocalSemanticInterpreter::class);
        $phrases = [
            'بدي MMA',
            'بدي فنون قتالية مختلطة',
            'اعمللي خطة MMA',
            'I want an MMA program',
        ];
        foreach ($phrases as $phrase) {
            $result = $local->interpret($phrase, false);
            $this->assertSame('training_plan_create', $result['intent'], $phrase);
            $disc = $result['constraints']['discipline'] ?? $result['entities']['discipline'] ?? null;
            $this->assertSame('mma', $disc, $phrase);
        }
    }

    public function test_group_e_replace_exercise_paraphrases(): void
    {
        $interpreter = app(CoachFollowUpInterpreter::class);
        $engine = app(TrainingPlanEngine::class);
        $plan = $engine->generateFromMessage(
            'Give me a 4-day MMA training plan.',
            $this->minimalContext(),
            'en',
        );
        $this->assertNotNull($plan['plan']);
        $active = ['plan' => $plan['plan'], 'intent' => $plan['intent']];

        $phrases = [
            'بدل آخر تمرين باليوم الثاني' => 2,
            'غير آخر تمرين باليوم 2' => 2,
            'replace the last exercise on day 2' => 2,
            'ما بدي آخر حركة باليوم الثاني، عطيني بديل' => 2,
        ];
        foreach ($phrases as $phrase => $day) {
            $follow = $interpreter->interpret($phrase, $active);
            $this->assertTrue($follow['is_follow_up'], $phrase);
            $this->assertSame('replace_last_exercise', $follow['action'], $phrase);
            $this->assertSame($day, $follow['day'], $phrase);
        }
    }

    public function test_group_f_duration_paraphrases(): void
    {
        $extractor = app(\App\Services\Coach\Semantic\DeterministicEntityExtractor::class);
        $phrases = [
            'ساعتين' => 120,
            '120 دقيقة' => 120,
            'make every session two hours' => 120,
        ];
        foreach ($phrases as $phrase => $minutes) {
            $this->assertSame($minutes, $extractor->extract($phrase)['duration_minutes'], $phrase);
        }
    }

    public function test_novel_paraphrases_never_in_implementation(): void
    {
        $resolver = app(SemanticIntentResolver::class);
        $novel = [
            'خلينا نخلي الحصة التالتة أقوى شوي' => ['training_plan_modify', 'increase_difficulty', 3],
            'شو رأيك نرفع مستوى اليوم الثالث؟' => ['training_plan_modify', 'increase_difficulty', 3],
            'أنا بدي تمرين بالنادي مو بالبيت' => ['training_plan_create', null, null],
        ];
        foreach ($novel as $phrase => [$intent, $mod, $day]) {
            $contract = $resolver->resolve($phrase, $intent === 'training_plan_modify');
            $this->assertSame($intent, $contract->intent, $phrase);
            if ($mod !== null) {
                $this->assertSame($mod, $contract->modification(), $phrase);
                $this->assertSame($day, $contract->targetDay(), $phrase);
            }
            if ($phrase === 'أنا بدي تمرين بالنادي مو بالبيت') {
                $this->assertSame('gym', $contract->constraints['equipment_mode'] ?? $contract->entities['equipment_mode'] ?? null);
            }
        }
    }

    /** @return array<string, mixed> */
    private function minimalContext(): array
    {
        return [
            'primary_goal' => 'general_fitness',
            'goals' => ['primary_goal' => 'general_fitness'],
            'training' => ['days_per_week' => 3, 'session_duration_minutes' => 60],
            'equipment' => ['mode' => 'home', 'available' => []],
            'recovery' => ['injury_status' => 'none', 'injury_areas' => []],
        ];
    }
}
