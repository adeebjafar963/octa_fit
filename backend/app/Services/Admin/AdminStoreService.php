<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AdminStoreService
{
    public function listCategories(): \Illuminate\Support\Collection
    {
        return ProductCategory::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function createCategory(array $data): ProductCategory
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name_en']);

        return ProductCategory::query()->create($data);
    }

    public function updateCategory(ProductCategory $category, array $data): ProductCategory
    {
        if (isset($data['name_en']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name_en']);
        }
        $category->update($data);

        return $category->fresh();
    }

    public function deleteCategory(ProductCategory $category): void
    {
        $category->delete();
    }

    public function listProducts(?string $search = null, ?int $categoryId = null, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return Product::query()
            ->with('category')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->search($search)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createProduct(array $data): Product
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name_en']);

        return Product::query()->create($data)->load('category');
    }

    public function updateProduct(Product $product, array $data): Product
    {
        if (isset($data['name_en']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name_en']);
        }
        $product->update($data);

        return $product->fresh('category');
    }

    public function deleteProduct(Product $product): void
    {
        $product->delete();
    }
}
