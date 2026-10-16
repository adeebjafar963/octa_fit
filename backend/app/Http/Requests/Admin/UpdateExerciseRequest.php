<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'training_category_id' => ['sometimes', 'integer', 'exists:training_categories,id'],
            'name_en' => ['sometimes', 'string', 'max:160'],
            'name_ar' => ['sometimes', 'string', 'max:160'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'difficulty' => ['sometimes', 'string', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'instructions_en' => ['nullable', 'string', 'max:5000'],
            'instructions_ar' => ['nullable', 'string', 'max:5000'],
            'safety_notes_en' => ['nullable', 'string', 'max:2000'],
            'safety_notes_ar' => ['nullable', 'string', 'max:2000'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'target' => ['nullable', 'string', 'max:120'],
            'equipment' => ['nullable', 'string', 'max:64'],
            'primary_muscles' => ['nullable', 'array'],
            'primary_muscles.*' => ['string', 'max:64'],
            'secondary_muscles' => ['nullable', 'array'],
            'secondary_muscles.*' => ['string', 'max:64'],
            'metric_type' => ['sometimes', 'string', Rule::in(['reps', 'duration', 'rounds'])],
            'sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'reps_min' => ['nullable', 'integer', 'min:0', 'max:100'],
            'reps_max' => ['nullable', 'integer', 'min:0', 'max:100'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'rest_seconds' => ['nullable', 'integer', 'min:0', 'max:600'],
            'rounds' => ['nullable', 'integer', 'min:1', 'max:20'],
            'round_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'round_rest_seconds' => ['nullable', 'integer', 'min:0', 'max:600'],
            'is_active' => ['sometimes', 'boolean'],
            'is_demo' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
