<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $car      = $this->resource['car'];
        $trips    = $this->resource['trips'];
        $upcoming = $this->resource['upcoming'];

        return [
            'car'            => new CarResource($car),
            'current_km'     => $car->current_km,
            'kilometer_logs' => [
                'from'     => now()->subDays(7)->toDateString(),
                'to'       => now()->toDateString(),
                'total_km' => (float) $trips->sum('total_distance_km'),
                'trips'    => TripResource::collection($trips),
            ],
            'next_services'  => ServiceResource::collection($upcoming),
        ];
    }
}
