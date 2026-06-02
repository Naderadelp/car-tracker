<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'price_per_unit' => $this->price_per_unit,
            'effective_from' => $this->effective_from?->toDateString(),
            'created_at'     => $this->created_at,
        ];
    }
}
