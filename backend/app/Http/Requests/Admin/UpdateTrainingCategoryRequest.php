<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainingCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id ?? $this->route('category');

        return [
            'name_en' => ['sometimes', 'string', 'max:120'],
            'name_ar' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:140', Rule::unique('training_categories', 'slug')->ignore($id)],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'type' => ['sometimes', 'string', Rule::in(['bodybuilding', 'mma', 'home_calisthenics'])],
            'level' => ['sometimes', 'string', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
