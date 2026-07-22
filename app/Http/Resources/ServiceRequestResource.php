<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ServiceRequest */
class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'priority' => $this->priority,
            'source' => $this->source,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'description' => $this->description,
            'address' => $this->address,
            'customer_id' => $this->customer_id,
            'ticket_id' => $this->ticket_id,
            'work_order_id' => $this->work_order_id,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
