<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePostRequest extends FormRequest
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
            'content' => ['nullable', 'string', 'max:1000'],
            'image_url' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $content = trim((string) $this->input('content', ''));
            $image = trim((string) $this->input('image_url', ''));
            if ($content === '' && $image === '') {
                $validator->errors()->add('content', 'Post content or image is required.');
            }
        });
    }
}
