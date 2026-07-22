<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attachment metadata only. The storage disk and path are never exposed;
 * bytes stream through the gated download route, not the API payload.
 *
 * @mixin \App\Models\Attachment
 */
class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'size' => (int) $this->size,
            'size_formatted' => $this->size_formatted,
            'is_internal' => $this->is_internal,
            'created_at' => $this->created_at,
        ];
    }
}
