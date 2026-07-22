<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes whatever reply it is given. The CONTROLLER decides whether an
 * internal note is included in the set — this resource never filters.
 *
 * @mixin \App\Models\TicketReply
 */
class TicketReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'author_type' => $this->author_type,
            'author_label' => $this->author_label,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'created_at' => $this->created_at,
        ];
    }
}
