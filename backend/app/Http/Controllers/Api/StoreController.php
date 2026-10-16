<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\AddCartItemRequest;
use App\Http\Requests\Store\CheckoutRequest;
use App\Http\Requests\Store\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\ProductResource;
use App\Services\StoreService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StoreController extends Controller
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {}

    public function categories(): JsonResponse
    {
        $categories = $this->storeService->listCategories();

        return ApiResponse::success(
            data: ProductCategoryResource::collection($categories)->resolve(),
            message: 'Store categories retrieved',
        );
    }

    public function products(Request $request): JsonResponse
    {
        $paginator = $this->storeService->listProducts($request->all());

        return ApiResponse::success(
            data: [
                'items' => ProductResource::collection($paginator->items())->resolve(),
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

    public function showProduct(string $product): JsonResponse
    {
        $model = $this->storeService->findProduct($product);
        if ($model === null) {
            return ApiResponse::error('Product not found', 404);
        }

        return ApiResponse::success(
            data: (new ProductResource($model))->resolve(),
            message: 'Product retrieved',
        );
    }

    public function cart(Request $request): JsonResponse
    {
        $cart = $this->storeService->getCart($request->user());

        return ApiResponse::success(
            data: (new CartResource($cart))->resolve(),
            message: 'Cart retrieved',
        );
    }

    public function addCartItem(AddCartItemRequest $request): JsonResponse
    {
        try {
            $cart = $this->storeService->addToCart(
                $request->user(),
                (int) $request->validated('product_id'),
                (int) $request->validated('quantity'),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to add to cart', 422, $e->errors());
        }

        return ApiResponse::success(
            data: (new CartResource($cart))->resolve(),
            message: 'Item added to cart',
        );
    }

    public function updateCartItem(UpdateCartItemRequest $request, int $cartItem): JsonResponse
    {
        try {
            $cart = $this->storeService->updateCartItem(
                $request->user(),
                $cartItem,
                (int) $request->validated('quantity'),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to update cart item', 422, $e->errors());
        }

        return ApiResponse::success(
            data: (new CartResource($cart))->resolve(),
            message: 'Cart item updated',
        );
    }

    public function removeCartItem(Request $request, int $cartItem): JsonResponse
    {
        $cart = $this->storeService->removeCartItem($request->user(), $cartItem);

        return ApiResponse::success(
            data: (new CartResource($cart))->resolve(),
            message: 'Cart item removed',
        );
    }

    public function clearCart(Request $request): JsonResponse
    {
        $cart = $this->storeService->clearCart($request->user());

        return ApiResponse::success(
            data: (new CartResource($cart))->resolve(),
            message: 'Cart cleared',
        );
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $order = $this->storeService->checkout(
                $request->user(),
                $validated['payment_method'],
                $validated['notes'] ?? null,
                $validated['payment_phone'] ?? null,
            );
        } catch (ValidationException $e) {
            return ApiResponse::error('Checkout failed', 422, $e->errors());
        }

        return ApiResponse::success(
            data: (new OrderResource($order))->resolve(),
            message: 'Order placed successfully',
            status: 201,
        );
    }

    public function orders(Request $request): JsonResponse
    {
        $paginator = $this->storeService->listOrders(
            $request->user(),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => OrderResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Orders retrieved',
        );
    }

    public function showOrder(Request $request, int $order): JsonResponse
    {
        $model = $this->storeService->findOrderForUser($request->user(), $order);
        if ($model === null) {
            return ApiResponse::error('Order not found', 404);
        }

        return ApiResponse::success(
            data: (new OrderResource($model))->resolve(),
            message: 'Order retrieved',
        );
    }
}
