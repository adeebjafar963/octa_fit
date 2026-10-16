<?php

namespace App\Http\Resources;

use App\Services\StoreService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Cart */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var StoreService $store */
        $store = app(StoreService::class);
        $subtotal = $store->cartSubtotal($this->resource);

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'items_count' => $store->cartItemCount($this->resource),
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ];
    }
}
