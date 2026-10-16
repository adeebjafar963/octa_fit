<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExerciseVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title_en' => ['sometimes', 'string', 'max:160'],
            'title_ar' => ['sometimes', 'string', 'max:160'],
            'video_url' => ['sometimes', 'string', 'max:500'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'is_placeholder' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
