<?php

namespace App\Services\Admin;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class AdminOrderService
{
    public const ALLOWED_STATUSES = [
        Order::STATUS_PENDING,
        Order::STATUS_CONFIRMED,
        Order::STATUS_PROCESSING,
        Order::STATUS_COMPLETED,
        Order::STATUS_CANCELLED,
    ];

    public function list(?string $search = null, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return Order::query()
            ->with('user')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('order_number', 'like', $like)
                        ->orWhereHas('user', function ($u) use ($like) {
                            $u->where('name', 'like', $like)->orWhere('email', 'like', $like);
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?Order
    {
        return Order::query()->with(['user', 'items'])->find($id);
    }

    public function updateStatus(Order $order, string $status): Order
    {
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new RuntimeException('Invalid order status.');
        }

        $order->status = $status;
        $order->save();

        return $order->fresh(['user', 'items']);
    }
}
