<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BookingType */
class BookingTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'duration_minutes' => (int) $this->duration_minutes,
            'buffer_before_minutes' => (int) $this->buffer_before_minutes,
            'buffer_after_minutes' => (int) $this->buffer_after_minutes,
            'total_minutes' => $this->total_minutes,
            'price_cents' => (int) $this->price_cents,
            'price_formatted' => $this->price_formatted,
            'assigned_user_id' => $this->assigned_user_id,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'position' => (int) $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ]),
        ];
    }
}
