<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'car_id'            => $this->car_id,
            'start_lat'         => $this->start_lat,
            'start_lng'         => $this->start_lng,
            'end_lat'           => $this->end_lat,
            'end_lng'           => $this->end_lng,
            'total_distance_km' => $this->total_distance_km,
            // Gap F5
            'started_at'        => $this->started_at?->toISOString(),
            'ended_at'          => $this->ended_at?->toISOString(),
            'duration_seconds'  => $this->duration_seconds,
            'max_speed_kmh'     => $this->max_speed_kmh,
            'created_at'        => $this->created_at,
        ];
    }
}
