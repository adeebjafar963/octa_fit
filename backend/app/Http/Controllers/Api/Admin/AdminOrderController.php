<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\Admin\AdminOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly AdminOrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orderService->list(
            $request->query('search'),
            $request->query('status'),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => collect($paginator->items())->map(fn (Order $o) => $this->summary($o)),
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

    public function show(Order $order): JsonResponse
    {
        $model = $this->orderService->find($order->id);
        if ($model === null) {
            return ApiResponse::error('Order not found', 404);
        }

        return ApiResponse::success(data: $this->detail($model), message: 'Order retrieved');
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        try {
            $updated = $this->orderService->updateStatus($order, $request->validated('status'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(data: $this->detail($updated), message: 'Order status updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Order $o): array
    {
        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'status' => $o->status,
            'total' => (float) $o->total,
            'created_at' => optional($o->created_at)?->toIso8601String(),
            'customer' => $o->relationLoaded('user') && $o->user
                ? ['id' => $o->user->id, 'name' => $o->user->name, 'email' => $o->user->email]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Order $o): array
    {
        return [
            ...$this->summary($o),
            'subtotal' => (float) $o->subtotal,
            'payment_method' => $o->payment_method,
            'notes' => $o->notes,
            'items' => $o->relationLoaded('items')
                ? $o->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name_en' => $item->product_name_en,
                    'product_name_ar' => $item->product_name_ar,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                ])->values()->all()
                : [],
        ];
    }
}
