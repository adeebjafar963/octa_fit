<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:13', 'max:100'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'height_cm' => ['nullable', 'numeric', 'min:100', 'max:250'],
            'weight_kg' => ['nullable', 'numeric', 'min:30', 'max:300'],
            'training_goal' => ['nullable', 'string', Rule::in([
                'muscle_gain', 'fat_loss', 'strength', 'endurance',
                'general_fitness', 'mma', 'mobility', 'rehabilitation',
            ])],
            'locale' => ['nullable', 'string', 'in:en,ar'],
            'fitness_level' => ['nullable', 'string', Rule::in([
                'beginner', 'intermediate', 'advanced',
            ])],
            'training_experience' => ['nullable', 'string', Rule::in([
                'none', 'under_1_year', '1_3_years', '3_plus_years',
            ])],
            'training_frequency' => ['nullable', 'integer', 'min:1', 'max:7'],
            'session_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:180'],
            'training_location' => ['nullable', 'string', Rule::in([
                'gym', 'home', 'outdoor', 'mixed',
            ])],
            'equipment' => ['nullable', 'array', 'max:20'],
            'equipment.*' => ['string', 'max:64'],
            'secondary_goals' => ['nullable', 'array', 'max:5'],
            'secondary_goals.*' => ['string', 'max:64'],
            'activity_level' => ['nullable', 'string', Rule::in([
                'sedentary', 'light', 'moderate', 'active', 'very_active',
            ])],
            'dietary_preference' => ['nullable', 'string', 'max:64'],
            'meals_per_day' => ['nullable', 'integer', 'min:1', 'max:8'],
            'sleep_hours' => ['nullable', 'numeric', 'min:3', 'max:14'],
            'injury_status' => ['nullable', 'string', Rule::in([
                'none', 'historical', 'current',
            ])],
            'injury_areas' => ['nullable', 'array', 'max:10'],
            'injury_areas.*' => ['string', 'max:64'],
            'injury_description' => ['nullable', 'string', 'max:1000'],
            'professional_evaluation' => ['nullable', 'boolean'],
            'onboarding_step' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];
    }
}
