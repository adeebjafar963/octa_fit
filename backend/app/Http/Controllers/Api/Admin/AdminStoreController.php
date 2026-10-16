<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductCategoryRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductCategoryRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Admin\AdminStoreService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreController extends Controller
{
    public function __construct(
        private readonly AdminStoreService $storeService,
    ) {}

    public function categories(): JsonResponse
    {
        $items = $this->storeService->listCategories()->map(fn (ProductCategory $c) => [
            'id' => $c->id,
            'name_en' => $c->name_en,
            'name_ar' => $c->name_ar,
            'slug' => $c->slug,
            'is_active' => (bool) $c->is_active,
            'sort_order' => $c->sort_order,
        ]);

        return ApiResponse::success(data: ['items' => $items], message: 'Product categories');
    }

    public function storeCategory(StoreProductCategoryRequest $request): JsonResponse
    {
        $category = $this->storeService->createCategory($request->validated());

        return ApiResponse::success(data: $category->toArray(), message: 'Category created', status: 201);
    }

    public function updateCategory(UpdateProductCategoryRequest $request, ProductCategory $category): JsonResponse
    {
        $updated = $this->storeService->updateCategory($category, $request->validated());

        return ApiResponse::success(data: $updated->toArray(), message: 'Category updated');
    }

    public function destroyCategory(ProductCategory $category): JsonResponse
    {
        $this->storeService->deleteCategory($category);

        return ApiResponse::success(message: 'Category deleted');
    }

    public function products(Request $request): JsonResponse
    {
        $paginator = $this->storeService->listProducts(
            $request->query('search'),
            $request->query('category_id') !== null ? (int) $request->query('category_id') : null,
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => collect($paginator->items())->map(fn (Product $p) => $this->productPayload($p)),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Products retrieved',
        );
    }

    public function storeProduct(StoreProductRequest $request): JsonResponse
    {
        $product = $this->storeService->createProduct($request->validated());

        return ApiResponse::success(data: $this->productPayload($product), message: 'Product created', status: 201);
    }

    public function updateProduct(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $updated = $this->storeService->updateProduct($product, $request->validated());

        return ApiResponse::success(data: $this->productPayload($updated), message: 'Product updated');
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        $this->storeService->deleteProduct($product);

        return ApiResponse::success(message: 'Product deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $p): array
    {
        return [
            'id' => $p->id,
            'category_id' => $p->category_id,
            'name_en' => $p->name_en,
            'name_ar' => $p->name_ar,
            'slug' => $p->slug,
            'price' => (float) $p->price,
            'stock_quantity' => (int) $p->stock_quantity,
            'image_url' => $p->image_url,
            'sku' => $p->sku,
            'is_active' => (bool) $p->is_active,
            'category' => $p->relationLoaded('category') && $p->category
                ? ['id' => $p->category->id, 'name_en' => $p->category->name_en]
                : null,
        ];
    }
}
