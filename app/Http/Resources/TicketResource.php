<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Ticket */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'priority' => $this->priority,
            'priority_label' => $this->priority_label,
            'is_open' => $this->is_open,
            'customer_id' => $this->customer_id,
            'service_request_id' => $this->service_request_id,
            'project_id' => $this->project_id,
            'assigned_user_id' => $this->assigned_user_id,
            'last_reply_at' => $this->last_reply_at,
            'last_reply_by' => $this->last_reply_by,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ]),
            'replies' => TicketReplyResource::collection($this->whenLoaded('replies')),
            'work_orders' => WorkOrderResource::collection($this->whenLoaded('workOrders')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
