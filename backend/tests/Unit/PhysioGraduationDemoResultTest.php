<?php

namespace Tests\Unit;

use App\Services\Physio\Demo\PhysioDemoCaseCatalog;
use App\Services\Physio\Demo\PhysioDemoCaseResolver;
use App\Services\Physio\Demo\PhysioDemoScenarioService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhysioGraduationDemoResultTest extends TestCase
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
    public function all_fifty_cases_resolve_and_have_structured_impressions(): void
    {
        $this->assertCount(50, $this->catalog->all());

        foreach ($this->catalog->all() as $case) {
            $payloadEn = $this->service->buildResultPayload($case, 'en');
            $payloadAr = $this->service->buildResultPayload($case, 'ar');

            $this->assertTrue($payloadEn['structured'] ?? false, $case['case_id']);
            $this->assertTrue($payloadEn['demo'] ?? false, $case['case_id']);
            $this->assertNotEmpty($payloadEn['initial_clinical_impression'], $case['case_id'].' EN impression');
            $this->assertNotEmpty($payloadAr['initial_clinical_impression'], $case['case_id'].' AR impression');
            $this->assertNotEmpty($payloadEn['key_findings'], $case['case_id'].' findings');
            $this->assertGreaterThanOrEqual(3, count($payloadEn['key_findings']), $case['case_id']);
            $this->assertNotEmpty($payloadEn['next_step'], $case['case_id'].' next step');
            $this->assertNotEmpty($payloadEn['evidence_basis'], $case['case_id'].' evidence');
            $this->assertIsArray($payloadEn['evidence_basis']);
            $this->assertNotEmpty($payloadEn['self_management']['today'] ?? [], $case['case_id']);
            $this->assertNotEmpty($payloadAr['self_management']['today'] ?? [], $case['case_id'].' AR today');

            $blob = mb_strtolower(json_encode($payloadEn, JSON_UNESCAPED_UNICODE) ?: '');
            $this->assertStringNotContainsString('you have a herniated', $blob);
            $this->assertStringNotContainsString('definitive diagnosis', $blob);
            $this->assertStringNotContainsString('تم تشخيصك', mb_strtolower(json_encode($payloadAr, JSON_UNESCAPED_UNICODE) ?: ''));
        }
    }

    #[Test]
    public function non_urgent_cases_have_complete_exercise_cards(): void
    {
        foreach ($this->catalog->all() as $case) {
            if (($case['escalation_level'] ?? '') === 'URGENT') {
                continue;
            }
            $payload = $this->service->buildResultPayload($case, 'en');
            $this->assertNotEmpty($payload['recommended_exercises'], $case['case_id']);
            foreach ($payload['recommended_exercises'] as $ex) {
                $this->assertNotEmpty($ex['name'] ?? null, $case['case_id']);
                $this->assertNotEmpty($ex['purpose'] ?? null, $case['case_id']);
                $this->assertNotEmpty($ex['difficulty'] ?? null, $case['case_id']);
                $this->assertNotEmpty($ex['instructions'] ?? [], $case['case_id']);
                $this->assertNotEmpty($ex['sets'] ?? null, $case['case_id']);
                $this->assertTrue(
                    ! empty($ex['reps']) || ! empty($ex['duration']) || ! empty($ex['reps_or_duration']),
                    $case['case_id'].' dosage'
                );
                $this->assertNotEmpty($ex['rest'] ?? null, $case['case_id']);
                $this->assertNotEmpty($ex['safety_note'] ?? null, $case['case_id']);
            }
        }
    }

    #[Test]
    public function urgent_cases_do_not_primary_prescribe_exercise(): void
    {
        foreach ($this->catalog->all() as $case) {
            if (($case['escalation_level'] ?? '') !== 'URGENT') {
                continue;
            }
            $payload = $this->service->buildResultPayload($case, 'en');
            $this->assertSame([], $payload['recommended_exercises'], $case['case_id']);
            $this->assertNotEmpty($payload['medical_priority_message'], $case['case_id']);
            $this->assertSame('urgent', $payload['risk_level']);
        }
    }

    #[Test]
    public function demo_fallback_resolves_freeform_body_region_inputs(): void
    {
        $samples = [
            ['body_area' => 'neck', 'symptoms' => 'ألم رقبة بعد الحاسوب', 'pain_level' => 4],
            ['body_area' => 'lower_back', 'symptoms' => 'ألم ظهر بعد رفع', 'pain_level' => 6],
            ['body_area' => 'shoulder', 'symptoms' => 'shoulder pain overhead', 'pain_level' => 5],
            ['body_area' => 'knee', 'symptoms' => 'knee pain running', 'pain_level' => 4],
            ['body_area' => 'ankle', 'symptoms' => 'ankle sprain', 'pain_level' => 5],
            ['body_area' => 'legs', 'symptoms' => 'achilles pain after running', 'pain_level' => 4],
            ['body_area' => 'lower_back', 'symptoms' => 'saddle numbness weakness', 'pain_level' => 8],
            ['body_area' => 'other', 'symptoms' => 'pain with fever', 'pain_level' => 7],
        ];

        foreach ($samples as $input) {
            $case = $this->resolver->resolveForDemoFallback($input);
            $this->assertNotNull($case, json_encode($input));
            $payload = $this->service->buildResultPayload($case, 'ar');
            $this->assertTrue($payload['structured']);
            $this->assertNotEmpty($payload['clinical_impression'] ?? $payload['initial_clinical_impression']);
        }
    }

    #[Test]
    public function arabic_and_english_rendering_payloads_are_populated(): void
    {
        $case = $this->catalog->byId(1);
        $this->assertNotNull($case);
        $en = $this->service->buildResultPayload($case, 'en');
        $ar = $this->service->buildResultPayload($case, 'ar');
        $this->assertSame('en', $en['language']);
        $this->assertSame('ar', $ar['language']);
        $this->assertNotSame($en['initial_clinical_impression'], $ar['initial_clinical_impression']);
        $this->assertNotEmpty($ar['recommended_exercises'][0]['instructions'] ?? []);
        $this->assertNotEmpty($en['recommended_exercises'][0]['instructions'] ?? []);
    }
}
