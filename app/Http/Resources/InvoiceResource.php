<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read-only view of an Order that settles service-desk work. Payment-gateway
 * internals (intent ids, idempotency keys, card tokens) are never exposed.
 *
 * @mixin \App\Models\Order
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'financial_status' => $this->financial_status,
            'financial_badge' => $this->financial_badge,
            'currency' => $this->currency,
            'subtotal_cents' => (int) $this->subtotal_cents,
            'tax_cents' => (int) $this->tax_cents,
            'shipping_cents' => (int) $this->shipping_cents,
            'discount_cents' => (int) $this->discount_cents,
            'total_cents' => (int) $this->total_cents,
            'total_formatted' => $this->total_formatted,
            'refunded_cents' => (int) $this->refunded_cents,
            'paid_at' => $this->paid_at,
            'work_order_id' => $this->work_order_id,
            'project_id' => $this->project_id,
            'customer_id' => $this->customer_id,
            'email' => $this->email,
            'created_at' => $this->created_at,

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => (int) $item->quantity,
                'unit_price_cents' => (int) $item->unit_price_cents,
                'total_cents' => (int) $item->total_cents,
            ])->values()),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
