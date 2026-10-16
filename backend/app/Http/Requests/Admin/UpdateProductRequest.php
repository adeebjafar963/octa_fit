<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id ?? $this->route('product');

        return [
            'category_id' => ['sometimes', 'integer', 'exists:product_categories,id'],
            'name_en' => ['sometimes', 'string', 'max:160'],
            'name_ar' => ['sometimes', 'string', 'max:160'],
            'slug' => ['sometimes', 'string', 'max:180', Rule::unique('products', 'slug')->ignore($id)],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'sku' => ['nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
            'is_demo' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
