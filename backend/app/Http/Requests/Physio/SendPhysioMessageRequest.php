<?php

namespace App\Http\Requests\Physio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendPhysioMessageRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:2000'],
            'language' => ['nullable', 'string', Rule::in(['en', 'ar'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('message') && is_string($this->input('message'))) {
            $this->merge(['message' => trim($this->input('message'))]);
        }
    }
}
