<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('content') && is_string($this->input('content'))) {
            $this->merge(['content' => trim($this->input('content'))]);
        }
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }
}
