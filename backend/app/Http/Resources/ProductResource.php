<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            'price' => number_format((float) $this->price, 2, '.', ''),
            'compare_at_price' => $this->compare_at_price !== null
                ? number_format((float) $this->compare_at_price, 2, '.', '')
                : null,
            'stock_quantity' => $this->stock_quantity,
            'in_stock' => $this->stock_quantity > 0,
            'image_url' => $this->image_url,
            'sku' => $this->sku,
            'is_active' => (bool) $this->is_active,
            'is_demo' => (bool) $this->is_demo,
            'category' => ProductCategoryResource::make($this->whenLoaded('category')),
        ];
    }
}
