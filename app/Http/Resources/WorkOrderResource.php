<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WorkOrder */
class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'notes' => $this->notes,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_changeable' => $this->is_changeable,
            'is_billable' => $this->is_billable,
            'scheduled_at' => $this->scheduled_at,
            'duration_minutes' => $this->duration_minutes,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'address' => $this->address,
            'subtotal_cents' => (int) $this->subtotal_cents,
            'subtotal_formatted' => $this->subtotal_formatted,
            'currency' => $this->currency,
            'customer_id' => $this->customer_id,
            'ticket_id' => $this->ticket_id,
            'project_id' => $this->project_id,
            'assigned_user_id' => $this->assigned_user_id,
            'invoice_order_id' => $this->invoice_order_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ]),
            'items' => WorkOrderItemResource::collection($this->whenLoaded('items')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
