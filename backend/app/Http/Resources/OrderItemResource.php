<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name_en' => $this->product_name_en,
            'product_name_ar' => $this->product_name_ar,
            'quantity' => $this->quantity,
            'unit_price' => number_format((float) $this->unit_price, 2, '.', ''),
            'line_total' => number_format((float) $this->line_total, 2, '.', ''),
        ];
    }
}
