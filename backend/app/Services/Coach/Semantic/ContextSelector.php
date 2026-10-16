<?php

namespace App\Services\Coach\Semantic;

/**
 * Selects only relevant context slices per intent.
 */
class ContextSelector
{
    /**
     * @param  array<string, mixed>  $fullContext
     * @return array<string, mixed>
     */
    public function select(array $fullContext, SemanticIntentContract $intent): array
    {
        $req = $intent->contextRequirements;
        $needsProfile = (bool) ($req['profile'] ?? false);
        $skipProfile = in_array($intent->intent, [
            'nutrition_lookup',
            'general_sports_knowledge',
            'training_exercise_recommendation',
            'ambiguous_request',
        ], true) && ! $needsProfile;

        if ($skipProfile) {
            return [
                'locale' => $fullContext['locale'] ?? 'en',
                'language' => $fullContext['language'] ?? 'en',
            ];
        }

        if (! $needsProfile && in_array($intent->intent, ['training_plan_modify', 'training_plan_resume'], true)) {
            // Plan modifications need minimal profile (injuries/allergies only).
            return [
                'locale' => $fullContext['locale'] ?? 'en',
                'language' => $fullContext['language'] ?? 'en',
                'recovery' => $fullContext['recovery'] ?? [],
                'injury_status' => $fullContext['injury_status'] ?? 'none',
                'injury_areas' => $fullContext['injury_areas'] ?? [],
            ];
        }

        return $fullContext;
    }
}
