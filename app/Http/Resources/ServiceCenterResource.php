<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCenterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'brand_id'    => $this->brand_id,
            'name'        => $this->name,
            'address'     => $this->address,
            'open_at'     => $this->open_at?->format('H:i'),
            'close_at'    => $this->close_at?->format('H:i'),
            'mobile'      => $this->mobile,
            'lat'         => $this->lat,
            'lng'         => $this->lng,
            'is_open'     => $this->is_open,
            'distance_km' => isset($this->distance_km) ? round((float) $this->distance_km, 2) : null,
            'brand'       => new BrandResource($this->whenLoaded('brand')),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
