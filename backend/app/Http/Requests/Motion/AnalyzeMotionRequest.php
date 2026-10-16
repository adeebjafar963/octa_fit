<?php

namespace App\Http\Requests\Motion;

use App\Services\MotionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyzeMotionRequest extends FormRequest
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
            'exercise' => ['required', 'string', 'max:40', Rule::in(MotionService::supportedExercises())],
            'language' => ['nullable', 'string', Rule::in(['en', 'ar'])],
            'video' => [
                'required',
                'file',
                'max:'.MotionService::MAX_UPLOAD_KB,
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-matroska,application/octet-stream',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exercise.in' => 'Unsupported exercise.',
            'video.required' => 'A video file is required.',
            'video.max' => 'The video may not be greater than 20 MB.',
            'video.mimetypes' => 'Unsupported video format.',
        ];
    }
}
