<?php

namespace Src\Domain\Vehicle\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'user_id'               => $this->user_id,
            'brand'                 => $this->brand,
            'model'                 => $this->model,
            'year'                  => $this->year,
            'current_mileage'       => $this->current_mileage,
            'has_warranty'          => $this->has_warranty,
            'warranty_limit_km'     => $this->warranty_limit_km,
            'warranty_expiry_date'  => $this->warranty_expiry_date?->toDateString(),
            'created_at'            => $this->created_at->toISOString(),
            'updated_at'            => $this->updated_at->toISOString(),
        ];
    }
}
