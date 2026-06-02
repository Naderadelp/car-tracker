<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CarResource;

class ReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'car_id'       => $this->car_id,
            'remind_on'    => $this->remind_on?->toDateString(),
            'remind_at_km' => $this->remind_at_km,
            'title'        => $this->title,
            'description'  => $this->description,
            'notified_at'  => $this->notified_at?->toISOString(),
            'created_at'   => $this->created_at,
            'car'          => new CarResource($this->whenLoaded('car')),
        ];
    }
}
