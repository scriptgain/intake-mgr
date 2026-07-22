<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A catalog service (a Product), read-only. Price is derived from the lowest
 * active variant via the model's price_from_cents accessor.
 *
 * @mixin \App\Models\Product
 */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'description' => $this->description,
            'status' => $this->status,
            'price_from_cents' => (int) $this->price_from_cents,
            'price_from_formatted' => $this->price_from_formatted,
        ];
    }
}
