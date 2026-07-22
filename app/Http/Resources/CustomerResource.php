<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A minimal, safe view of a customer. Never exposes the password hash,
 * remember token or marketing/notes internals.
 *
 * @mixin \App\Models\Customer
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'created_at' => $this->created_at,

            'orders_count' => $this->whenCounted('orders'),
            'tickets_count' => $this->whenCounted('tickets'),
        ];
    }
}
