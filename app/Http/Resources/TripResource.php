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
            'start_time'        => $this->start_time,
            'end_time'          => $this->end_time,
            'start_lat'         => $this->start_lat,
            'start_lng'         => $this->start_lng,
            'end_lat'           => $this->end_lat,
            'end_lng'           => $this->end_lng,
            'total_distance_km' => $this->total_distance_km,
            'created_at'        => $this->created_at,
        ];
    }
}
