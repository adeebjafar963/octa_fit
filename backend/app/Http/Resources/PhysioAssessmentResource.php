<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PhysioAssessment */
class PhysioAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = is_array($this->result_payload) ? $this->result_payload : [];

        return [
            'id' => $this->id,
            'body_area' => $this->body_area,
            'symptoms' => $this->symptoms,
            'pain_level' => $this->pain_level,
            'duration' => $this->duration,
            'onset' => $this->onset,
            'trigger' => $this->trigger,
            'activity' => $this->activity,
            'additional_information' => $this->additional_information,
            'risk_level' => $this->risk_level,
            'requires_urgent_care' => (bool) $this->requires_urgent_care,
            'result' => [
                'summary' => $payload['summary'] ?? null,
                'possible_explanations' => $payload['possible_explanations'] ?? [],
                'self_care' => $payload['self_care'] ?? [],
                'avoid' => $payload['avoid'] ?? [],
                'professional_care' => $payload['professional_care'] ?? null,
                'red_flags' => $payload['red_flags'] ?? [],
                'clinical_assessment' => $payload['clinical_assessment'] ?? null,
                'initial_clinical_impression' => $payload['initial_clinical_impression']
                    ?? ($payload['clinical_impression'] ?? null),
                'clinical_impression' => $payload['clinical_impression']
                    ?? ($payload['initial_clinical_impression'] ?? null),
                'confidence' => $payload['confidence'] ?? null,
                'key_findings' => $payload['key_findings'] ?? [],
                'recommended_exercises' => $payload['recommended_exercises'] ?? [],
                'exercise_dosage' => $payload['exercise_dosage'] ?? null,
                'activity_advice' => $payload['activity_advice'] ?? null,
                'self_management' => $payload['self_management'] ?? null,
                'what_to_avoid' => $payload['what_to_avoid'] ?? ($payload['avoid'] ?? []),
                'follow_up' => $payload['follow_up'] ?? ($payload['professional_care'] ?? null),
                'next_step' => $payload['next_step'] ?? ($payload['follow_up'] ?? null),
                'evidence_basis' => $payload['evidence_basis'] ?? null,
                'escalation_level' => $payload['escalation_level'] ?? null,
                'severity' => $payload['severity'] ?? ($payload['escalation_level'] ?? null),
                'expected_clinical_pattern' => $payload['expected_clinical_pattern'] ?? null,
                'title' => $payload['title'] ?? null,
                'subtitle' => $payload['subtitle'] ?? null,
                'medical_priority_message' => $payload['medical_priority_message'] ?? null,
                'case_id' => $payload['case_id'] ?? null,
                'demo_case_id' => $payload['demo_case_id'] ?? ($payload['case_id'] ?? null),
                'demo' => (bool) ($payload['demo'] ?? false),
                'structured' => (bool) ($payload['structured'] ?? ($payload['demo'] ?? false)),
                'language' => $payload['language'] ?? null,
            ],
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'messages' => PhysioMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
