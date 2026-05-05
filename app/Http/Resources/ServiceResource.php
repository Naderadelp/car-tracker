<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'car_model_id' => $this->car_model_id,
            'km'           => $this->km,
            'price'        => $this->price,
            'items_count'  => $this->whenCounted('items'),
            'remaining_km' => $this->when(
                $request->route('car') !== null,
                fn () => $this->km - $request->route('car')->current_km,
            ),
            'car_model'    => new CarModelResource($this->whenLoaded('carModel')),
            'items'        => ItemResource::collection($this->whenLoaded('items')),
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
