<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreService
{
    /**
     * @return Collection<int, ProductCategory>
     */
    public function listCategories(): Collection
    {
        return ProductCategory::query()
            ->active()
            ->withCount(['products as products_count' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->get();
    }

    public function listProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()
            ->active()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! empty($filters['category'])) {
            $category = $filters['category'];
            $query->whereHas('category', function ($q) use ($category) {
                $q->active();
                if (is_numeric($category)) {
                    $q->where('id', (int) $category);
                } else {
                    $q->where('slug', $category);
                }
            });
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 50);

        return $query->paginate($perPage);
    }

    public function findProduct(int|string $idOrSlug): ?Product
    {
        $query = Product::query()->active()->with('category');

        if (is_numeric($idOrSlug)) {
            return $query->where('id', (int) $idOrSlug)->first();
        }

        return $query->where('slug', $idOrSlug)->first();
    }

    public function getOrCreateCart(User $user): Cart
    {
        return Cart::query()->firstOrCreate(['user_id' => $user->id]);
    }

    public function getCart(User $user): Cart
    {
        $cart = $this->getOrCreateCart($user);
        $cart->load(['items.product.category']);

        return $cart;
    }

    public function addToCart(User $user, int $productId, int $quantity): Cart
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        $product = Product::query()->active()->find($productId);
        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => ['Product not found or inactive.'],
            ]);
        }

        if ($product->stock_quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Product is out of stock.'],
            ]);
        }

        $cart = $this->getOrCreateCart($user);
        $item = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        $newQty = ($item?->quantity ?? 0) + $quantity;
        if ($newQty > $product->stock_quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Requested quantity exceeds available stock.'],
            ]);
        }

        if ($item === null) {
            CartItem::query()->create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
            ]);
        } else {
            $item->quantity = $newQty;
            $item->unit_price = $product->price;
            $item->save();
        }

        return $this->getCart($user);
    }

    public function updateCartItem(User $user, int $cartItemId, int $quantity): Cart
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        $cart = $this->getOrCreateCart($user);
        $item = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('id', $cartItemId)
            ->first();

        if ($item === null) {
            throw ValidationException::withMessages([
                'cart_item' => ['Cart item not found.'],
            ]);
        }

        $product = Product::query()->active()->find($item->product_id);
        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => ['Product is no longer available.'],
            ]);
        }

        if ($quantity > $product->stock_quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Requested quantity exceeds available stock.'],
            ]);
        }

        $item->quantity = $quantity;
        $item->unit_price = $product->price;
        $item->save();

        return $this->getCart($user);
    }

    public function removeCartItem(User $user, int $cartItemId): Cart
    {
        $cart = $this->getOrCreateCart($user);
        CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('id', $cartItemId)
            ->delete();

        return $this->getCart($user);
    }

    public function clearCart(User $user): Cart
    {
        $cart = $this->getOrCreateCart($user);
        $cart->items()->delete();

        return $this->getCart($user);
    }

    public function checkout(User $user, string $paymentMethod, ?string $notes = null, ?string $paymentPhone = null): Order
    {
        if (! in_array($paymentMethod, Order::selectablePaymentMethods(), true)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Invalid payment method.'],
            ]);
        }

        $storedPhone = null;
        if ($paymentMethod === Order::PAYMENT_SYRIATEL_CASH) {
            $phone = trim((string) $paymentPhone);
            if ($phone === '') {
                throw ValidationException::withMessages([
                    'payment_phone' => ['Mobile number is required.'],
                ]);
            }
            if (! preg_match(Order::SYRIATEL_PHONE_PATTERN, $phone)) {
                throw ValidationException::withMessages([
                    'payment_phone' => ['Invalid mobile number.'],
                ]);
            }
            $storedPhone = $phone;
        }

        return DB::transaction(function () use ($user, $paymentMethod, $notes, $storedPhone) {
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($cart === null) {
                throw ValidationException::withMessages([
                    'cart' => ['Cart is empty.'],
                ]);
            }

            $items = CartItem::query()
                ->where('cart_id', $cart->id)
                ->with('product')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Cart is empty.'],
                ]);
            }

            $subtotal = '0.00';
            $prepared = [];

            foreach ($items as $item) {
                $product = Product::query()->lockForUpdate()->find($item->product_id);

                if ($product === null || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        'cart' => ['One or more products are no longer available.'],
                    ]);
                }

                if ($item->quantity > $product->stock_quantity) {
                    throw ValidationException::withMessages([
                        'cart' => ['Insufficient stock for '.$product->name_en.'.'],
                    ]);
                }

                $unitPrice = number_format((float) $product->price, 2, '.', '');
                $lineTotal = bcmul($unitPrice, (string) $item->quantity, 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $prepared[] = [
                    'product' => $product,
                    'quantity' => $item->quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => Order::STATUS_PENDING,
                'payment_method' => $paymentMethod,
                'payment_phone' => $storedPhone,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'notes' => $notes,
            ]);

            foreach ($prepared as $row) {
                /** @var Product $product */
                $product = $row['product'];

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name_en' => $product->name_en,
                    'product_name_ar' => $product->name_ar,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'line_total' => $row['line_total'],
                ]);

                $product->stock_quantity -= $row['quantity'];
                $product->save();
            }

            $cart->items()->delete();

            return $order->load('items');
        });
    }

    public function listOrders(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 50));
    }

    public function findOrderForUser(User $user, int $orderId): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('id', $orderId)
            ->with('items')
            ->first();
    }

    public function cartSubtotal(Cart $cart): string
    {
        $sum = '0.00';
        foreach ($cart->items as $item) {
            $sum = bcadd($sum, $item->lineTotal(), 2);
        }

        return $sum;
    }

    public function cartItemCount(Cart $cart): int
    {
        return (int) $cart->items->sum('quantity');
    }

    private function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'OCTA-'.$date.'-';

        $latest = Order::query()
            ->where('order_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('order_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
