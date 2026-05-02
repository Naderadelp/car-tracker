<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'vehicle_id'   => $this->vehicle_id,
            'type'         => $this->type,
            'expiry_date'  => $this->expiry_date?->toDateString(),
            'created_at'   => $this->created_at->toISOString(),
            'updated_at'   => $this->updated_at->toISOString(),
            'user'         => new UserResource($this->whenLoaded('user')),
            'media'        => MediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
