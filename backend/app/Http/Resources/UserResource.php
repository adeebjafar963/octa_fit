<?php

namespace App\Http\Resources;

use App\Services\ProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $completion = app(ProfileCompletionService::class)->evaluate(
            $this->relationLoaded('profile') ? $this->profile : null
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'role' => $this->whenLoaded('role', function () {
                if ($this->role === null) {
                    return null;
                }

                return [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                    'display_name' => $this->role->display_name,
                ];
            }),
            'profile_complete' => $completion['profile_complete'],
            'completion_percentage' => $completion['completion_percentage'],
            'missing_fields' => $completion['missing_fields'],
            'profile' => $this->whenLoaded('profile', function () use ($request, $completion) {
                if ($this->profile === null) {
                    return null;
                }

                $p = $this->profile;
                $viewer = $request->user();
                $isOwner = $viewer !== null && (int) $viewer->id === (int) $this->id;
                $isAdmin = $viewer !== null && method_exists($viewer, 'isAdmin') && $viewer->isAdmin();

                $base = [
                    'avatar_path' => $p->avatar_path,
                    'age' => $p->age,
                    'gender' => $p->gender,
                    'height_cm' => $p->height_cm !== null ? (float) $p->height_cm : null,
                    'weight_kg' => $p->weight_kg !== null ? (float) $p->weight_kg : null,
                    'water_goal_ml' => $p->water_goal_ml,
                    'water_goal_is_custom' => (bool) $p->water_goal_is_custom,
                    'training_goal' => $p->training_goal,
                    'locale' => $p->locale,
                    'fitness_level' => $p->fitness_level,
                    'training_experience' => $p->training_experience,
                    'training_frequency' => $p->training_frequency,
                    'session_duration_minutes' => $p->session_duration_minutes,
                    'training_location' => $p->training_location,
                    'equipment' => $p->equipment ?? [],
                    'secondary_goals' => $p->secondary_goals ?? [],
                    'activity_level' => $p->activity_level,
                    'dietary_preference' => $p->dietary_preference,
                    'meals_per_day' => $p->meals_per_day,
                    'sleep_hours' => $p->sleep_hours !== null ? (float) $p->sleep_hours : null,
                    'injury_status' => $p->injury_status,
                    'onboarding_step' => (int) ($p->onboarding_step ?? 0),
                    'profile_completed_at' => $completion['profile_completed_at'],
                ];

                // Sensitive injury details: owner or admin only.
                if ($isOwner || $isAdmin) {
                    $base['injury_areas'] = $p->injury_areas ?? [];
                    $base['injury_description'] = $p->injury_description;
                    $base['professional_evaluation'] = $p->professional_evaluation;
                }

                return $base;
            }),
        ];
    }
}
