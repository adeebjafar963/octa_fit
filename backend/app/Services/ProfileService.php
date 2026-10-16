<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    /** @var list<string> */
    public const PROFILE_FIELDS = [
        'age',
        'gender',
        'height_cm',
        'weight_kg',
        'water_goal_ml',
        'water_goal_is_custom',
        'training_goal',
        'locale',
        'fitness_level',
        'training_experience',
        'training_frequency',
        'session_duration_minutes',
        'training_location',
        'equipment',
        'secondary_goals',
        'activity_level',
        'dietary_preference',
        'meals_per_day',
        'sleep_hours',
        'injury_status',
        'injury_areas',
        'injury_description',
        'professional_evaluation',
        'onboarding_step',
    ];

    public function __construct(
        private readonly ProfileCompletionService $completionService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        DB::transaction(function () use ($user, $data) {
            if (array_key_exists('name', $data)) {
                $user->name = $data['name'];
                $user->save();
            }

            $profile = $user->profile ?? Profile::query()->create([
                'user_id' => $user->id,
                'locale' => 'en',
            ]);

            foreach (self::PROFILE_FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $profile->{$field} = $data[$field];
                }
            }

            $profile->save();
            $this->completionService->syncCompletionTimestamp($profile);
        });

        return $user->fresh(['role', 'profile']);
    }

    /**
     * @return array{
     *   profile_complete: bool,
     *   completion_percentage: int,
     *   missing_fields: list<string>,
     *   onboarding_step: int,
     *   profile_completed_at: string|null
     * }
     */
    public function completion(User $user): array
    {
        $user->loadMissing('profile');

        return $this->completionService->evaluate($user->profile);
    }
}
