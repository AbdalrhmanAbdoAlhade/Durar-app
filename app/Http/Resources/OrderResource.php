<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type,
            'auction_id' => $this->auction_id,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'coupon' => new CouponResource($this->whenLoaded('coupon')),
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'shipping_fee' => (float) $this->shipping_fee,
            'total' => (float) $this->total,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'shipping_address' => $this->shipping_address,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
