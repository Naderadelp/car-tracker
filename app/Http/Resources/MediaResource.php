<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'uuid'            => $this->uuid,
            'file_name'       => $this->file_name,
            'mime_type'       => $this->mime_type,
            'size'            => $this->size,
            'collection_name' => $this->collection_name,
            'created_at'      => $this->created_at->toISOString(),
        ];
    }
}
