<?php

namespace Tests\Feature;

use Database\Seeders\TrainingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\Coach\CoachEvalBank;
use Tests\Support\Coach\CoachEvalCaseEvaluator;
use Tests\TestCase;

/**
 * Master Intelligence Evaluation Bank — data-driven levels 1–8.
 * Reports measured coverage; does not invent answers from the bank.
 */
class CoachMasterEvaluationBankTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TrainingSeeder::class);
    }

    public function test_bank_structure_meets_coverage_requirements(): void
    {
        $payload = CoachEvalBank::payload();
        $cases = $payload['cases'];
        $this->assertGreaterThanOrEqual(350, count($cases), 'Bank must have >= 350 unique scenarios');

        $messages = array_column($cases, 'user_message');
        $this->assertSame(count($messages), count(array_unique($messages)), 'All user_message values must be unique');

        $required = [
            'id', 'category', 'subcategory', 'language', 'difficulty', 'user_message',
            'expected_domain', 'expected_intent', 'expected_operation', 'expected_entities',
            'expected_constraints', 'expected_capabilities', 'expected_context_level',
            'expected_response_mode', 'must_not_route_to', 'expected_behavior',
            'validation_rules', 'generalization_group',
        ];
        foreach ($cases as $c) {
            foreach ($required as $field) {
                $this->assertArrayHasKey($field, $c, "Case {$c['id']} missing {$field}");
            }
        }

        $byCat = [];
        $byLang = [];
        foreach ($cases as $c) {
            $byCat[$c['category']] = ($byCat[$c['category']] ?? 0) + 1;
            $byLang[$c['language']] = ($byLang[$c['language']] ?? 0) + 1;
        }

        $minimums = [
            'basic_sports_qa' => 30,
            'nutrition_lookup' => 35,
            'nutrition_comparison' => 20,
            'gym_plan' => 30,
            'bodybuilding' => 30,
            'mma' => 35,
            'home_workout' => 25,
            'calisthenics' => 20,
            'follow_up_training' => 30,
            'safety_injury' => 25,
            'prompt_injection' => 20,
            'english' => 34,
            'mixed_language' => 25,
            'compound_constraints' => 30,
            'novel_paraphrase' => 50,
            'demonstration' => 20,
        ];
        foreach ($minimums as $cat => $min) {
            $this->assertGreaterThanOrEqual($min, $byCat[$cat] ?? 0, "Category {$cat} below minimum {$min}");
        }

        $this->assertGreaterThanOrEqual(400, ($byLang['ar'] ?? 0) + ($byLang['en'] ?? 0) + ($byLang['mixed'] ?? 0));
        $this->assertGreaterThanOrEqual(50, $byCat['novel_paraphrase'] ?? 0);
    }

    public function test_level1_intent_classification_report(): void
    {
        $report = $this->runLevel('intent');
        $this->writeReport('coach_eval_intent_report.json', $report);
        $this->assertGreaterThan(0, $report['total']);
        // Measured coverage — critical subset hard-gated separately.
        $this->assertGreaterThanOrEqual(0.55, $report['pass_rate'], 'Intent pass rate unexpectedly collapsed: '.$report['pass_rate']);
    }

    public function test_level2_entity_extraction_report(): void
    {
        $cases = array_values(array_filter(
            CoachEvalBank::cases(),
            static fn (array $c) => ($c['expected_entities'] ?? []) !== [] || ($c['expected_constraints'] ?? []) !== [],
        ));
        $report = $this->runCases($cases, 'entities');
        $this->writeReport('coach_eval_entity_report.json', $report);
        $this->assertGreaterThan(0, $report['total']);
    }

    public function test_level3_capability_routing_report(): void
    {
        $report = $this->runLevel('routing');
        $this->writeReport('coach_eval_routing_report.json', $report);
        $this->assertGreaterThan(0, $report['total']);
    }

    public function test_level4_engine_execution_subset(): void
    {
        $cases = array_values(array_filter(
            CoachEvalBank::cases(),
            static function (array $c): bool {
                if (($c['expected_intent'] ?? '') !== 'training_plan_create') {
                    return false;
                }
                if (($c['execute_engine'] ?? false) === true) {
                    return true;
                }

                return ($c['category'] ?? '') === 'compound_constraints';
            },
        ));
        $cases = array_slice($cases, 0, 40);
        $report = $this->runCases($cases, 'engine');
        $this->writeReport('coach_eval_engine_report.json', $report);
        $this->assertGreaterThan(0, $report['total']);
    }

    public function test_level6_safety_and_injection(): void
    {
        $cases = CoachEvalBank::casesInCategories(['safety_injury', 'prompt_injection', 'allergy_constraints']);
        $report = $this->runCases($cases, 'safety');
        $this->writeReport('coach_eval_safety_report.json', $report);
        $this->assertGreaterThan(0, $report['total']);
    }

    public function test_level7_novel_paraphrase_generalization(): void
    {
        $cases = CoachEvalBank::casesInCategories(['novel_paraphrase']);
        $report = $this->runCases($cases, 'generalization');
        $this->writeReport('coach_eval_novel_report.json', $report);
        $this->assertGreaterThanOrEqual(50, $report['total']);
    }

    public function test_level8_critical_hard_gate_must_pass(): void
    {
        $evaluator = app(CoachEvalCaseEvaluator::class);
        $failures = [];
        foreach (CoachEvalBank::criticalCases() as $case) {
            $intent = (string) ($case['expected_intent'] ?? '');
            $level = 'routing';
            if ($intent === 'training_plan_create'
                && in_array(($case['category'] ?? ''), ['mma', 'gym_plan', 'compound_constraints', 'demonstration'], true)) {
                $level = 'engine';
            }
            $result = $evaluator->evaluate($case, $level);
            if (! $result['pass']) {
                $failures[] = $case['id'].' | '.$case['user_message'].' | '.implode(',', $result['failures']);
            }
        }
        $this->assertSame([], $failures, "Critical eval cases failed:\n".implode("\n", $failures));
    }

    /**
     * @return array{total:int,passed:int,failed:int,pass_rate:float,failures:list<array<string,mixed>>,by_category:array<string,array{total:int,passed:int}>}
     */
    private function runLevel(string $level): array
    {
        return $this->runCases(CoachEvalBank::cases(), $level);
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     * @return array{total:int,passed:int,failed:int,pass_rate:float,failures:list<array<string,mixed>>,by_category:array<string,array{total:int,passed:int}>}
     */
    private function runCases(array $cases, string $level): array
    {
        $evaluator = app(CoachEvalCaseEvaluator::class);
        $passed = 0;
        $failures = [];
        $byCat = [];
        foreach ($cases as $case) {
            $cat = (string) ($case['category'] ?? 'unknown');
            $byCat[$cat] ??= ['total' => 0, 'passed' => 0];
            $byCat[$cat]['total']++;
            $result = $evaluator->evaluate($case, $level);
            if ($result['pass']) {
                $passed++;
                $byCat[$cat]['passed']++;
            } else {
                $failures[] = [
                    'id' => $case['id'] ?? null,
                    'category' => $cat,
                    'msg' => $case['user_message'] ?? null,
                    'failures' => $result['failures'],
                    'got_intent' => $result['got']['intent'] ?? null,
                ];
            }
        }
        $total = count($cases);

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
            'pass_rate' => $total > 0 ? round($passed / $total, 4) : 0.0,
            'failures' => $failures,
            'by_category' => $byCat,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeReport(string $filename, array $report): void
    {
        $path = storage_path('app/'.$filename);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
