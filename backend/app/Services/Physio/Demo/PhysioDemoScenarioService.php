<?php

namespace App\Services\Physio\Demo;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Isolated graduation-demo layer for AI Physiotherapist.
 * Active only when config('services.ai.physio_demo_mode') is true.
 * Does NOT alter normal production Physio AI routing when disabled.
 */
class PhysioDemoScenarioService
{
    public function __construct(
        private readonly PhysioDemoCaseResolver $resolver,
        private readonly PhysioDemoCaseCatalog $catalog,
        private readonly PhysioDemoLocalizedContent $localizedContent = new PhysioDemoLocalizedContent,
        private readonly PhysioDemoExerciseLibrary $exerciseLibrary = new PhysioDemoExerciseLibrary,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('services.ai.physio_demo_mode', false);
    }

    /**
     * @param  array{
     *   body_area?: string|null,
     *   symptoms?: string|null,
     *   pain_level?: int|null,
     *   duration?: string|null,
     *   onset?: string|null,
     *   trigger?: string|null,
     *   activity?: string|null,
     *   additional_information?: string|null,
     *   language?: string|null
     * }  $input
     * @return array<string, mixed>|null
     */
    public function tryHandle(User $user, array $input, ?string $language = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        // Graduation reliability: always map to a curated case while demo mode is on.
        $case = $this->resolver->resolveForDemoFallback($input);
        if ($case === null) {
            return null;
        }

        Log::info('physio.demo_case.hit', [
            'case_id' => $case['case_id'] ?? null,
            'user_id' => $user->id,
            'body_area' => $input['body_area'] ?? null,
        ]);

        return $this->buildAiCompatibleResult($case, $language ?? ($input['language'] ?? 'en'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function canonicalInputs(): array
    {
        return $this->catalog->canonicalInputs();
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    public function buildAiCompatibleResult(array $case, string $language = 'en'): array
    {
        $language = in_array($language, ['en', 'ar'], true) ? $language : 'en';
        $escalation = strtoupper((string) ($case['escalation_level'] ?? 'LOW'));
        [$risk, $urgent] = $this->mapEscalation($escalation);

        $locale = $this->localizedContent->forCase($case, $language);
        $rawExercises = is_array($case['recommended_exercises'] ?? null)
            ? $case['recommended_exercises']
            : [];

        // Urgent/red-flag demos: no primary rehab program.
        if (in_array($escalation, ['URGENT'], true)) {
            $rawExercises = [];
        }

        $exercises = $this->exerciseLibrary->enrich($rawExercises, $language);
        $findings = is_array($locale['key_findings'] ?? null) ? $locale['key_findings'] : [];
        $avoid = is_array($locale['avoid'] ?? null) ? array_values($locale['avoid']) : [];
        $redFlags = is_array($locale['red_flags'] ?? null) ? array_values($locale['red_flags']) : [];
        $evidence = is_array($locale['evidence_basis'] ?? null) ? array_values($locale['evidence_basis']) : [];
        $selfManagement = is_array($locale['self_management'] ?? null) ? $locale['self_management'] : ['today' => [], 'next_days' => []];
        $impression = trim((string) ($locale['clinical_impression'] ?? ''));
        $subtitle = trim((string) ($locale['subtitle'] ?? ''));
        $title = trim((string) ($locale['title'] ?? ''));
        $nextStep = trim((string) ($locale['next_step'] ?? ''));
        $medicalPriority = isset($locale['medical_priority_message'])
            ? trim((string) $locale['medical_priority_message'])
            : null;
        if ($medicalPriority === '') {
            $medicalPriority = null;
        }

        $findingLines = [];
        foreach ($findings as $finding) {
            if (is_array($finding)) {
                $line = trim(((string) ($finding['title'] ?? '')).': '.((string) ($finding['detail'] ?? '')));
                if ($line !== ':') {
                    $findingLines[] = $line;
                }
            } elseif (is_string($finding) && trim($finding) !== '') {
                $findingLines[] = trim($finding);
            }
        }

        $selfCare = [];
        foreach (($selfManagement['today'] ?? []) as $item) {
            if (is_string($item) && trim($item) !== '') {
                $selfCare[] = trim($item);
            }
        }
        foreach (($selfManagement['next_days'] ?? []) as $item) {
            if (is_string($item) && trim($item) !== '') {
                $selfCare[] = trim($item);
            }
        }
        foreach ($exercises as $ex) {
            $selfCare[] = trim(sprintf(
                '%s — %s × %s',
                (string) ($ex['name'] ?? 'Exercise'),
                (string) ($ex['sets'] ?? '—'),
                (string) ($ex['reps_or_duration'] ?? '—'),
            ));
        }
        if ($selfCare === [] && $urgent) {
            $selfCare[] = $language === 'ar'
                ? 'لا تبدأ برنامجاً تأهيلياً قبل التقييم الطبي العاجل.'
                : 'Do not start a rehabilitation program before urgent medical assessment.';
        }

        $assessmentLabel = $language === 'ar'
            ? 'التقييم السريري الأولي بناءً على المعلومات المقدمة.'
            : 'Initial clinical assessment based on the information provided.';

        $summary = $assessmentLabel;
        if ($impression !== '') {
            $summary .= "\n\n".($language === 'ar' ? 'الانطباع السريري الأولي:' : 'Initial Clinical Impression:')."\n".$impression;
        }

        $dosageParts = [];
        foreach ($exercises as $ex) {
            $dosageParts[] = trim(sprintf(
                '%s: %s × %s (rest %s)',
                (string) ($ex['name'] ?? ''),
                (string) ($ex['sets'] ?? '—'),
                (string) ($ex['reps_or_duration'] ?? '—'),
                (string) ($ex['rest'] ?? '—'),
            ));
        }

        return [
            'success' => true,
            'risk_level' => $risk,
            'requires_urgent_care' => $urgent,
            'summary' => $summary,
            'possible_explanations' => $findingLines,
            'self_care' => $selfCare,
            'avoid' => $avoid,
            'professional_care' => $nextStep,
            'red_flags' => $redFlags,
            'provider' => 'physio_demo',
            'language' => $language,

            // Structured premium UI payload:
            'demo' => true,
            'structured' => true,
            'case_id' => (string) ($case['case_id'] ?? ''),
            'demo_case_id' => (string) ($case['case_id'] ?? ''),
            'title' => $title,
            'subtitle' => $subtitle,
            'clinical_assessment' => $assessmentLabel,
            'initial_clinical_impression' => $impression,
            'clinical_impression' => $impression,
            'confidence' => (string) ($case['confidence'] ?? 'Moderate'),
            'key_findings' => $findings,
            'recommended_exercises' => $exercises,
            'exercise_dosage' => $dosageParts === []
                ? ($language === 'ar' ? 'لا يُوصى ببرنامج تمارين كاستجابة أولية.' : 'No exercise program as the primary response.')
                : implode('; ', $dosageParts),
            'activity_advice' => implode(' ', array_map('strval', $selfManagement['today'] ?? [])),
            'self_management' => $selfManagement,
            'what_to_avoid' => $avoid,
            'follow_up' => $nextStep,
            'next_step' => $nextStep,
            'evidence_basis' => $evidence,
            'escalation_level' => $escalation,
            'severity' => $escalation,
            'expected_clinical_pattern' => (string) ($case['expected_clinical_pattern'] ?? $title),
            'medical_priority_message' => $medicalPriority,
        ];
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    public function buildResultPayload(array $case, string $language = 'en'): array
    {
        return $this->buildAiCompatibleResult($case, $language);
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function mapEscalation(string $escalation): array
    {
        return match ($escalation) {
            'URGENT' => ['urgent', true],
            'HIGH' => ['high', false],
            'MODERATE' => ['moderate', false],
            default => ['low', false],
        };
    }
}
