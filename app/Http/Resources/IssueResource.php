<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'car_id'      => $this->car_id,
            'occurred_at' => $this->occurred_at?->toDateString(),
            'title'       => $this->title,
            'severity'    => $this->severity,
            'summary'     => $this->summary,
            'solution'    => $this->solution,
            'note'        => $this->note,
            'resolved'    => $this->resolved_at !== null,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'has_photo'   => $this->getMedia(\App\Models\Issue::PHOTO_COLLECTION)->isNotEmpty(),
            // Metadata only — the file lives on the private disk and is served
            // through the secure download route, never a public URL.
            'media'       => MediaResource::collection($this->whenLoaded('media')),
            'created_at'  => $this->created_at->toISOString(),
            'updated_at'  => $this->updated_at->toISOString(),
        ];
    }
}
