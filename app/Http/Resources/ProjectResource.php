<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Project */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'progress_percent' => $this->progress_percent,
            'starts_on' => $this->starts_on,
            'due_on' => $this->due_on,
            'completed_at' => $this->completed_at,
            'customer_id' => $this->customer_id,
            'assigned_user_id' => $this->assigned_user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ]),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
            'work_orders' => WorkOrderResource::collection($this->whenLoaded('workOrders')),
        ];
    }
}
