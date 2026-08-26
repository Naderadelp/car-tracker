<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            // FR-030 — Latin fallback when no Arabic variant is recorded.
            'name_ar'    => $this->name_ar ?? $this->name,
            'price'      => $this->price,
            'services'   => ServiceResource::collection($this->whenLoaded('services')),
            'pivot'      => $this->when(isset($this->pivot), fn () => [
                'car_id' => $this->pivot->car_id ?? null,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
