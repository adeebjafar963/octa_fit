<?php

namespace Tests\Unit;

use App\Services\Physio\Demo\PhysioDemoCaseCatalog;
use App\Services\Physio\Demo\PhysioDemoCaseResolver;
use App\Services\Physio\Demo\PhysioDemoScenarioService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhysioGraduationDemoCasesTest extends TestCase
{
    private PhysioDemoCaseCatalog $catalog;

    private PhysioDemoCaseResolver $resolver;

    private PhysioDemoScenarioService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new PhysioDemoCaseCatalog;
        $this->resolver = new PhysioDemoCaseResolver($this->catalog);
        $this->service = new PhysioDemoScenarioService($this->resolver, $this->catalog);
    }

    #[Test]
    public function all_fifty_curated_cases_exist_with_deterministic_ids(): void
    {
        $cases = $this->catalog->all();
        $this->assertCount(50, $cases);

        for ($i = 1; $i <= 50; $i++) {
            $expectedId = sprintf('PHYS-%02d', $i);
            $case = $this->catalog->byId($i);
            $this->assertNotNull($case, "Missing case {$expectedId}");
            $this->assertSame($expectedId, $case['case_id']);
            $this->assertNotEmpty($case['body_area']);
            $this->assertNotEmpty($case['symptoms']);
            $this->assertArrayHasKey('pain_level', $case);
            $this->assertNotEmpty($case['duration']);
            $this->assertNotEmpty($case['onset']);
            $this->assertNotEmpty($case['cause'] ?? $case['trigger'] ?? '');
            $this->assertNotEmpty($case['expected_clinical_pattern']);
            $this->assertNotEmpty($case['initial_clinical_impression']);
            $this->assertContains($case['confidence'], ['Low', 'Moderate', 'High']);
            $this->assertNotEmpty($case['key_findings']);
            $this->assertIsArray($case['recommended_exercises']);
            $this->assertNotEmpty($case['exercise_dosage']);
            $this->assertNotEmpty($case['activity_advice']);
            $this->assertNotEmpty($case['what_to_avoid']);
            $this->assertNotEmpty($case['red_flags']);
            $this->assertContains($case['escalation_level'], ['LOW', 'MODERATE', 'HIGH', 'URGENT']);
            $this->assertNotEmpty($case['follow_up']);
            $this->assertNotEmpty($case['evidence_basis']);
        }
    }

    #[Test]
    public function all_fifty_cases_resolve_deterministically_from_form_input(): void
    {
        $resolved = 0;

        foreach ($this->catalog->all() as $case) {
            $input = $case['input'];
            $match = $this->resolver->resolve([
                'body_area' => $input['body_area'],
                'symptoms' => $input['symptoms'],
                'pain_level' => $input['pain_level'],
                'duration' => $input['duration'],
                'onset' => $input['onset'],
                'trigger' => $input['trigger'],
                'activity' => $input['activity'] ?? '',
                'additional_information' => $input['additional_information'] ?? '',
            ]);

            $this->assertNotNull($match, 'Failed to resolve '.$case['case_id']);
            $this->assertSame($case['case_id'], $match['case_id']);
            $this->assertSame($case['expected_clinical_pattern'], $match['expected_clinical_pattern']);
            $this->assertSame($case['escalation_level'], $match['escalation_level']);
            $resolved++;
        }

        $this->assertSame(50, $resolved);
    }

    #[Test]
    public function each_case_builds_structured_clinical_output_without_definitive_diagnosis(): void
    {
        $forbidden = [
            'You have a herniated disc',
            'You have a rotator cuff tear',
            'You have a meniscus tear',
            'definitive diagnosis',
            'Definitive Diagnosis',
        ];

        foreach ($this->catalog->all() as $case) {
            $payload = $this->service->buildResultPayload($case);

            $this->assertSame($case['case_id'], $payload['demo_case_id']);
            $this->assertSame($case['expected_clinical_pattern'], $payload['expected_clinical_pattern']);
            $this->assertSame($case['escalation_level'], $payload['escalation_level']);
            $this->assertArrayHasKey('clinical_assessment', $payload);
            $this->assertArrayHasKey('initial_clinical_impression', $payload);
            $this->assertArrayHasKey('confidence', $payload);
            $this->assertArrayHasKey('key_findings', $payload);
            $this->assertArrayHasKey('recommended_exercises', $payload);
            $this->assertArrayHasKey('exercise_dosage', $payload);
            $this->assertArrayHasKey('activity_advice', $payload);
            $this->assertArrayHasKey('what_to_avoid', $payload);
            $this->assertArrayHasKey('red_flags', $payload);
            $this->assertArrayHasKey('follow_up', $payload);
            $this->assertArrayHasKey('evidence_basis', $payload);
            $this->assertArrayHasKey('risk_level', $payload);

            $blob = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $this->assertIsString($blob);

            foreach ($forbidden as $phrase) {
                $this->assertStringNotContainsString(
                    $phrase,
                    $blob,
                    $case['case_id'].' must not use definitive diagnosis language'
                );
            }

            $impression = (string) ($payload['initial_clinical_impression'] ?? '');
            $this->assertTrue(
                str_contains(strtolower($impression), 'impression')
                || str_contains(strtolower($impression), 'compatible')
                || str_contains(strtolower($impression), 'consistent')
                || str_contains(strtolower($impression), 'preliminary')
                || str_contains(strtolower($impression), 'pattern'),
                $case['case_id'].' impression should use preliminary language'
            );
        }
    }

    #[Test]
    public function non_urgent_cases_have_exercise_plans_with_required_fields(): void
    {
        foreach ($this->catalog->all() as $case) {
            if (in_array($case['escalation_level'], ['HIGH', 'URGENT'], true) && $case['recommended_exercises'] === []) {
                continue;
            }

            if (in_array($case['escalation_level'], ['URGENT'], true)) {
                $this->assertSame([], $case['recommended_exercises'], $case['case_id']);

                continue;
            }

            if ($case['recommended_exercises'] === []) {
                continue;
            }

            foreach ($case['recommended_exercises'] as $exercise) {
                $this->assertNotEmpty($exercise['name'] ?? null, $case['case_id'].' exercise name');
                $this->assertNotEmpty($exercise['purpose'] ?? null, $case['case_id'].' exercise purpose');
                $this->assertNotEmpty($exercise['sets'] ?? null, $case['case_id'].' exercise sets');
                $this->assertNotEmpty($exercise['reps_or_duration'] ?? null, $case['case_id'].' reps/duration');
                $this->assertNotEmpty($exercise['intensity'] ?? null, $case['case_id'].' intensity');
                $this->assertNotEmpty($exercise['progression'] ?? null, $case['case_id'].' progression');
            }
        }
    }

    #[Test]
    public function safety_cases_forty_six_to_fifty_prioritize_escalation_over_rehab(): void
    {
        for ($i = 46; $i <= 50; $i++) {
            $case = $this->catalog->byId($i);
            $this->assertNotNull($case);
            $this->assertSame('URGENT', $case['escalation_level'], $case['case_id']);
            $this->assertSame([], $case['recommended_exercises'], $case['case_id'].' must not primary-prescribe rehab');
            $this->assertNotEmpty($case['red_flags'], $case['case_id']);

            $payload = $this->service->buildResultPayload($case);
            $this->assertSame('urgent', $payload['risk_level']);
            $this->assertSame([], $payload['recommended_exercises']);
            $this->assertTrue(
                str_contains(strtolower((string) $payload['follow_up']), 'urgent')
                || str_contains(strtolower((string) $payload['follow_up']), 'emergency')
                || str_contains(strtolower((string) $payload['follow_up']), 'medical'),
                $case['case_id'].' next step must escalate medically'
            );
        }
    }

    #[Test]
    public function named_graduation_spot_checks_match_expected_patterns(): void
    {
        $expectations = [
            1 => ['neck', 'LOW'],
            6 => ['lower_back', 'MODERATE'],
            11 => ['shoulder', 'MODERATE'],
            19 => ['knee', 'MODERATE'],
            27 => ['ankle', 'MODERATE'],
            33 => ['hip', 'MODERATE'],
            38 => ['legs', 'MODERATE'],
            45 => ['upper_back', 'MODERATE'],
            46 => ['lower_back', 'URGENT'],
            47 => ['lower_back', 'URGENT'],
            48 => ['neck', 'URGENT'],
            49 => ['knee', 'URGENT'],
            50 => ['other', 'URGENT'],
        ];

        foreach ($expectations as $id => [$body, $escalation]) {
            $case = $this->catalog->byId($id);
            $this->assertNotNull($case);
            $this->assertSame($body, $case['body_area'], sprintf('PHYS-%02d body', $id));
            $this->assertSame($escalation, $case['escalation_level'], sprintf('PHYS-%02d escalation', $id));

            $input = $case['input'];
            $resolved = $this->resolver->resolve([
                'body_area' => $input['body_area'],
                'symptoms' => $input['symptoms'],
                'pain_level' => $input['pain_level'],
                'duration' => $input['duration'],
                'onset' => $input['onset'],
                'trigger' => $input['trigger'],
                'activity' => $input['activity'] ?? '',
                'additional_information' => $input['additional_information'] ?? '',
            ]);
            $this->assertSame(sprintf('PHYS-%02d', $id), $resolved['case_id'] ?? null);
        }
    }

    #[Test]
    public function demo_mode_defaults_to_false_in_phpunit_config(): void
    {
        $this->assertFalse((bool) config('services.ai.physio_demo_mode'));
    }
}
