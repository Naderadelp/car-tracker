<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CarResource;
use App\Http\Resources\ServiceResource;

class CarLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'car_id'              => $this->car_id,
            'service_id'          => $this->service_id,
            // Gap F4 — what was done and who did it.
            'title'               => $this->title,
            'workshop'            => $this->workshop,
            'category'            => $this->category,
            'notes'               => $this->notes,
            'odometer_at_service' => $this->odometer_at_service,
            'actual_cost'         => $this->actual_cost,
            'performed_at'        => $this->performed_at?->toDateString(),
            'created_at'          => $this->created_at,
            'car'                 => new CarResource($this->whenLoaded('car')),
            'service'             => new ServiceResource($this->whenLoaded('service')),
        ];
    }
}
