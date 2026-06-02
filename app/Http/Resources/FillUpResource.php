<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FillUpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'car_id'     => $this->car_id,
            'liters'     => $this->liters,
            'odometer'   => $this->odometer,
            'cost_egp'   => $this->cost_egp,
            'fill_date'  => $this->fill_date?->toDateString(),
            'fuel_type'   => $this->fuel_type,
            'station_lat' => $this->station_lat,
            'station_lng' => $this->station_lng,
            'created_at' => $this->created_at,
        ];
    }
}
