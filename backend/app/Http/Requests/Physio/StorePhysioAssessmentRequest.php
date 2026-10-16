<?php

namespace App\Http\Requests\Physio;

use App\Services\PhysioService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhysioAssessmentRequest extends FormRequest
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
            'body_area' => ['required', 'string', 'max:80', Rule::in(PhysioService::bodyAreas())],
            'symptoms' => ['required', 'string', 'max:2000'],
            'pain_level' => ['nullable', 'integer', 'min:0', 'max:10'],
            'duration' => ['nullable', 'string', 'max:200'],
            'onset' => ['nullable', 'string', 'max:200'],
            'trigger' => ['nullable', 'string', 'max:500'],
            'activity' => ['nullable', 'string', 'max:200'],
            'additional_information' => ['nullable', 'string', 'max:2000'],
            'language' => ['nullable', 'string', Rule::in(['en', 'ar'])],
        ];
    }
}
