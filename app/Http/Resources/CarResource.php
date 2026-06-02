<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'user_id'              => $this->user_id,
            'brand_id'             => $this->brand_id,
            'car_model_id'         => $this->car_model_id,
            'current_km'           => $this->current_km,
            'tank_size'            => $this->tank_size,
            'has_warranty'         => $this->has_warranty,
            'warranty_limit_km'    => $this->warranty_limit_km,
            'warranty_expiry_date' => $this->warranty_expiry_date?->toDateString(),
            'brand'                => new BrandResource($this->whenLoaded('brand')),
            'car_model'            => new CarModelResource($this->whenLoaded('carModel')),
            'created_at'           => $this->created_at->toISOString(),
            'updated_at'           => $this->updated_at->toISOString(),
        ];
    }
}
