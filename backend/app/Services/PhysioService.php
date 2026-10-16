<?php

namespace App\Services;

use App\Models\PhysioAssessment;
use App\Models\PhysioMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PhysioService
{
    private const CONTEXT_LIMIT = 16;

    public function __construct(
        private readonly UserAiContextBuilder $aiContextBuilder,
        private readonly \App\Services\Physio\Demo\PhysioDemoScenarioService $demoScenarioService,
    ) {}

    private const BODY_AREAS = [
        'neck',
        'shoulder',
        'upper_back',
        'lower_back',
        'elbow',
        'wrist',
        'hand',
        'hip',
        'knee',
        'ankle',
        'foot',
        'legs',
        'other',
    ];

    /**
     * @return list<string>
     */
    public static function bodyAreas(): array
    {
        return self::BODY_AREAS;
    }

    public function listAssessments(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return PhysioAssessment::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * @param  array{
     *   body_area: string,
     *   symptoms: string,
     *   pain_level?: int|null,
     *   duration?: string|null,
     *   onset?: string|null,
     *   trigger?: string|null,
     *   activity?: string|null,
     *   additional_information?: string|null,
     *   language?: string|null
     * }  $data
     */
    public function createAssessment(User $user, array $data): PhysioAssessment
    {
        // Call AI first so provider failures do not leave orphan assessments.
        $draft = new PhysioAssessment([
            'body_area' => $data['body_area'],
            'symptoms' => $data['symptoms'],
            'pain_level' => $data['pain_level'] ?? null,
            'duration' => $data['duration'] ?? null,
            'onset' => $data['onset'] ?? null,
            'trigger' => $data['trigger'] ?? null,
            'activity' => $data['activity'] ?? null,
            'additional_information' => $data['additional_information'] ?? null,
        ]);

        $result = $this->callAssess($user, $draft, $data['language'] ?? null);

        $assessment = PhysioAssessment::query()->create([
            'user_id' => $user->id,
            'body_area' => $draft->body_area,
            'symptoms' => $draft->symptoms,
            'pain_level' => $draft->pain_level,
            'duration' => $draft->duration,
            'onset' => $draft->onset,
            'trigger' => $draft->trigger,
            'activity' => $draft->activity,
            'additional_information' => $draft->additional_information,
            'requires_urgent_care' => false,
        ]);

        $this->applyResult($assessment, $result);

        PhysioMessage::query()->create([
            'assessment_id' => $assessment->id,
            'role' => PhysioMessage::ROLE_USER,
            'content' => $this->formatUserAssessmentMessage($assessment),
        ]);

        PhysioMessage::query()->create([
            'assessment_id' => $assessment->id,
            'role' => PhysioMessage::ROLE_ASSISTANT,
            'content' => $this->formatAssistantMessage($result),
        ]);

        return $this->findForUser($user, $assessment->id) ?? $assessment->fresh();
    }

    public function findForUser(User $user, int $assessmentId): ?PhysioAssessment
    {
        return PhysioAssessment::query()
            ->where('user_id', $user->id)
            ->where('id', $assessmentId)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at')->orderBy('id')])
            ->first();
    }

    public function deleteAssessment(PhysioAssessment $assessment): void
    {
        $assessment->delete();
    }

    /**
     * @return array{user_message: PhysioMessage, assistant_message: PhysioMessage, assessment: PhysioAssessment}
     */
    public function sendMessage(User $user, PhysioAssessment $assessment, string $message, ?string $language = null): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('Message is required.');
        }

        $userMessage = PhysioMessage::query()->create([
            'assessment_id' => $assessment->id,
            'role' => PhysioMessage::ROLE_USER,
            'content' => $message,
        ]);

        $history = PhysioMessage::query()
            ->where('assessment_id', $assessment->id)
            ->where('id', '<', $userMessage->id)
            ->orderByDesc('id')
            ->limit(self::CONTEXT_LIMIT)
            ->get()
            ->sortBy('id')
            ->values()
            ->map(fn (PhysioMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->all();

        $result = $this->callFollowUp($user, $assessment, $message, $history, $language);
        $this->applyResult($assessment, $result);

        $assistantMessage = PhysioMessage::query()->create([
            'assessment_id' => $assessment->id,
            'role' => PhysioMessage::ROLE_ASSISTANT,
            'content' => $this->formatAssistantMessage($result),
        ]);

        $assessment->touch();

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'assessment' => $this->findForUser($user, $assessment->id) ?? $assessment->fresh(),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function applyResult(PhysioAssessment $assessment, array $result): void
    {
        $assessment->risk_level = $result['risk_level'] ?? null;
        $assessment->requires_urgent_care = (bool) ($result['requires_urgent_care'] ?? false);
        $assessment->result_payload = [
            'summary' => $result['summary'] ?? '',
            'possible_explanations' => $result['possible_explanations'] ?? [],
            'self_care' => $result['self_care'] ?? [],
            'avoid' => $result['avoid'] ?? [],
            'professional_care' => $result['professional_care'] ?? '',
            'red_flags' => $result['red_flags'] ?? [],
            'provider' => $result['provider'] ?? 'mock',
            'language' => $result['language'] ?? 'en',
            'clinical_assessment' => $result['clinical_assessment'] ?? null,
            'initial_clinical_impression' => $result['initial_clinical_impression'] ?? ($result['clinical_impression'] ?? null),
            'clinical_impression' => $result['clinical_impression'] ?? ($result['initial_clinical_impression'] ?? null),
            'confidence' => $result['confidence'] ?? null,
            'key_findings' => $result['key_findings'] ?? [],
            'recommended_exercises' => $result['recommended_exercises'] ?? [],
            'exercise_dosage' => $result['exercise_dosage'] ?? null,
            'activity_advice' => $result['activity_advice'] ?? null,
            'self_management' => $result['self_management'] ?? null,
            'what_to_avoid' => $result['what_to_avoid'] ?? ($result['avoid'] ?? []),
            'follow_up' => $result['follow_up'] ?? ($result['professional_care'] ?? null),
            'next_step' => $result['next_step'] ?? ($result['follow_up'] ?? null),
            'evidence_basis' => $result['evidence_basis'] ?? null,
            'escalation_level' => $result['escalation_level'] ?? null,
            'severity' => $result['severity'] ?? ($result['escalation_level'] ?? null),
            'expected_clinical_pattern' => $result['expected_clinical_pattern'] ?? null,
            'title' => $result['title'] ?? null,
            'subtitle' => $result['subtitle'] ?? null,
            'medical_priority_message' => $result['medical_priority_message'] ?? null,
            'case_id' => $result['case_id'] ?? null,
            'demo_case_id' => $result['demo_case_id'] ?? ($result['case_id'] ?? null),
            'demo' => (bool) ($result['demo'] ?? false),
            'structured' => (bool) ($result['structured'] ?? ($result['demo'] ?? false)),
        ];
        $assessment->save();
    }

    private function callAssess(User $user, PhysioAssessment $assessment, ?string $language): array
    {
        $user->loadMissing('profile');
        $locale = $user->profile?->locale;
        $resolvedLanguage = $language ?? (in_array($locale, ['en', 'ar'], true) ? $locale : null);

        $payload = [
            'body_area' => $assessment->body_area,
            'symptoms' => $assessment->symptoms,
            'pain_level' => $assessment->pain_level,
            'duration' => $assessment->duration,
            'onset' => $assessment->onset,
            'trigger' => $assessment->trigger,
            'activity' => $assessment->activity,
            'additional_information' => $assessment->additional_information,
            'user_context' => $this->aiContextBuilder->forPhysio($user),
        ];
        if ($resolvedLanguage !== null && in_array($resolvedLanguage, ['en', 'ar'], true)) {
            $payload['language'] = $resolvedLanguage;
        }

        // Graduation demo layer — isolated; only when PHYSIO_DEMO_MODE=true.
        $demo = $this->demoScenarioService->tryHandle($user, $payload, $resolvedLanguage);
        if ($demo !== null) {
            return $demo;
        }

        return $this->postToPhysio('/assess', $payload);
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    private function callFollowUp(User $user, PhysioAssessment $assessment, string $message, array $history, ?string $language): array
    {
        $user->loadMissing('profile');
        $locale = $user->profile?->locale;
        $resolvedLanguage = $language ?? (in_array($locale, ['en', 'ar'], true) ? $locale : null);

        $payload = [
            'message' => $message,
            'assessment' => [
                'body_area' => $assessment->body_area,
                'symptoms' => $assessment->symptoms,
                'pain_level' => $assessment->pain_level,
                'duration' => $assessment->duration,
                'onset' => $assessment->onset,
                'trigger' => $assessment->trigger,
                'activity' => $assessment->activity,
                'additional_information' => $assessment->additional_information,
            ],
            'conversation' => $history,
            'user_context' => $this->aiContextBuilder->forPhysio($user),
        ];
        if ($resolvedLanguage !== null && in_array($resolvedLanguage, ['en', 'ar'], true)) {
            $payload['language'] = $resolvedLanguage;
        }

        return $this->postToPhysio('/chat', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postToPhysio(string $path, array $payload): array
    {
        $baseUrl = rtrim((string) config('services.ai.physio_url'), '/');
        $token = (string) config('services.ai.internal_token');

        $request = Http::timeout(45)
            ->acceptJson()
            ->asJson();

        if ($token !== '') {
            $request = $request->withHeaders(['X-Internal-Token' => $token]);
        }

        $response = $request->post($baseUrl.$path, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to reach the AI Physiotherapist service.');
        }

        $body = $response->json();
        if (! is_array($body) || ! ($body['success'] ?? false)) {
            throw new RuntimeException('AI Physiotherapist returned an invalid response.');
        }

        $risk = $body['risk_level'] ?? null;
        if (! is_string($risk) || ! in_array($risk, ['low', 'moderate', 'high', 'urgent'], true)) {
            throw new RuntimeException('AI Physiotherapist returned an invalid risk level.');
        }

        $summary = $body['summary'] ?? null;
        if (! is_string($summary) || trim($summary) === '') {
            throw new RuntimeException('AI Physiotherapist returned an empty summary.');
        }

        return [
            'success' => true,
            'risk_level' => $risk,
            'requires_urgent_care' => (bool) ($body['requires_urgent_care'] ?? false),
            'summary' => trim($summary),
            'possible_explanations' => array_values(array_filter(
                is_array($body['possible_explanations'] ?? null) ? $body['possible_explanations'] : [],
                fn ($v) => is_string($v) && trim($v) !== '',
            )),
            'self_care' => array_values(array_filter(
                is_array($body['self_care'] ?? null) ? $body['self_care'] : [],
                fn ($v) => is_string($v) && trim($v) !== '',
            )),
            'avoid' => array_values(array_filter(
                is_array($body['avoid'] ?? null) ? $body['avoid'] : [],
                fn ($v) => is_string($v) && trim($v) !== '',
            )),
            'professional_care' => is_string($body['professional_care'] ?? null)
                ? trim($body['professional_care'])
                : '',
            'red_flags' => array_values(array_filter(
                is_array($body['red_flags'] ?? null) ? $body['red_flags'] : [],
                fn ($v) => is_string($v) && trim($v) !== '',
            )),
            'provider' => is_string($body['provider'] ?? null) ? $body['provider'] : 'mock',
            'language' => in_array($body['language'] ?? null, ['en', 'ar'], true) ? $body['language'] : 'en',
        ];
    }

    private function formatUserAssessmentMessage(PhysioAssessment $assessment): string
    {
        $parts = [
            'Body area: '.$assessment->body_area,
            'Symptoms: '.$assessment->symptoms,
        ];
        if ($assessment->pain_level !== null) {
            $parts[] = 'Pain level: '.$assessment->pain_level.'/10';
        }
        if ($assessment->duration) {
            $parts[] = 'Duration: '.$assessment->duration;
        }
        if ($assessment->onset) {
            $parts[] = 'Onset: '.$assessment->onset;
        }
        if ($assessment->trigger) {
            $parts[] = 'Trigger: '.$assessment->trigger;
        }
        if ($assessment->activity) {
            $parts[] = 'Activity: '.$assessment->activity;
        }
        if ($assessment->additional_information) {
            $parts[] = 'Additional: '.$assessment->additional_information;
        }

        return implode("\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatAssistantMessage(array $result): string
    {
        $lines = [
            (string) ($result['summary'] ?? ''),
        ];

        $explanations = $result['possible_explanations'] ?? [];
        if (is_array($explanations) && $explanations !== []) {
            $lines[] = '';
            $lines[] = 'Possible explanations:';
            foreach ($explanations as $item) {
                $lines[] = '• '.$item;
            }
        }

        $selfCare = $result['self_care'] ?? [];
        if (is_array($selfCare) && $selfCare !== []) {
            $lines[] = '';
            $lines[] = 'What you can do now:';
            foreach ($selfCare as $item) {
                $lines[] = '• '.$item;
            }
        }

        $avoid = $result['avoid'] ?? [];
        if (is_array($avoid) && $avoid !== []) {
            $lines[] = '';
            $lines[] = 'Avoid:';
            foreach ($avoid as $item) {
                $lines[] = '• '.$item;
            }
        }

        if (! empty($result['professional_care'])) {
            $lines[] = '';
            $lines[] = 'Professional care: '.$result['professional_care'];
        }

        $redFlags = $result['red_flags'] ?? [];
        if (is_array($redFlags) && $redFlags !== []) {
            $lines[] = '';
            $lines[] = 'Red flags:';
            foreach ($redFlags as $item) {
                $lines[] = '• '.$item;
            }
        }

        return trim(implode("\n", $lines));
    }
}
