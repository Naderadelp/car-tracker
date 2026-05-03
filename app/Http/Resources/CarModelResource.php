<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'brand_id'   => $this->brand_id,
            'name'       => $this->name,
            'model_year' => $this->model_year,
            'brand'      => new BrandResource($this->whenLoaded('brand')),
        ];
    }
}
