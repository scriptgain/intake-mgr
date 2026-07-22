<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WorkOrderItem */
class WorkOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => (int) $this->quantity,
            'unit_price_cents' => (int) $this->unit_price_cents,
            'unit_price_formatted' => $this->unit_price_formatted,
            'total_cents' => (int) $this->total_cents,
            'total_formatted' => $this->total_formatted,
        ];
    }
}
