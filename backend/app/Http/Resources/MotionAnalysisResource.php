<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MotionAnalysisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'exercise' => $data['exercise'] ?? null,
            'repetitions' => $data['repetitions'] ?? 0,
            'duration_seconds' => $data['duration_seconds'] ?? 0,
            'confidence' => $data['confidence'] ?? 0,
            'movement_quality' => $data['movement_quality'] ?? 'unknown',
            'observations' => $data['observations'] ?? [],
            'tips' => $data['tips'] ?? [],
            'metrics' => [],
            'provider' => $data['provider'] ?? null,
            'frames_analyzed' => $data['frames_analyzed'] ?? 0,
            'demo' => (bool) ($data['demo'] ?? false),
            'structured' => (bool) ($data['structured'] ?? false),
            'title' => $data['title'] ?? null,
            'analysis_label' => $data['analysis_label'] ?? null,
            'hero_subtitle' => $data['hero_subtitle'] ?? null,
            'overall_status' => $data['overall_status'] ?? null,
            'overall_summary' => $data['overall_summary'] ?? null,
            'movement_points' => $data['movement_points'] ?? [],
            'coaching_cues' => $data['coaching_cues'] ?? [],
            'common_mistakes' => $data['common_mistakes'] ?? [],
            'improvement_drills' => $data['improvement_drills'] ?? [],
            'next_attempt' => $data['next_attempt'] ?? [],
            'final_summary' => $data['final_summary'] ?? null,
            'disclaimer' => $data['disclaimer']
                ?? 'This analysis is educational and does not replace assessment by a qualified coach or healthcare professional. It is not a medical diagnosis.',
            'language' => $data['language'] ?? null,
        ];
    }
}
