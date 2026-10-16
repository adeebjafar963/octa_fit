<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_phone' => $this->payment_phone,
            'subtotal' => number_format((float) $this->subtotal, 2, '.', ''),
            'total' => number_format((float) $this->total, 2, '.', ''),
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
