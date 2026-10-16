<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('caption') && is_string($this->input('caption'))) {
            $this->merge(['caption' => trim($this->input('caption'))]);
        }
    }

    public function rules(): array
    {
        return [
            'media_url' => ['required', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:280'],
        ];
    }
}
