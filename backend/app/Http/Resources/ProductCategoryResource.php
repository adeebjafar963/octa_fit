<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductCategory */
class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'image_url' => $this->image_url,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
