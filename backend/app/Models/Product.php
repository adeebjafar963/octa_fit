<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id',
    'name_en',
    'name_ar',
    'slug',
    'description_en',
    'description_ar',
    'price',
    'compare_at_price',
    'stock_quantity',
    'image_url',
    'sku',
    'is_active',
    'is_demo',
    'sort_order',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name_en', 'like', $like)
                ->orWhere('name_ar', 'like', $like)
                ->orWhere('description_en', 'like', $like)
                ->orWhere('description_ar', 'like', $like)
                ->orWhere('sku', 'like', $like);
        });
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }
}
