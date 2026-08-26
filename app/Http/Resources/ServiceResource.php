<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'car_model_id' => $this->car_model_id,
            'km'           => $this->km,
            'price'        => $this->price,
            'items_count'  => $this->whenCounted('items'),
            'remaining_km' => $this->when(
                $request->route('car') !== null,
                fn () => $this->km - $request->route('car')->current_km,
            ),

            /*
             * Gap F2 — the grid greys completed intervals, highlights the next
             * one and shows later ones ahead of it. Without this the client has
             * to work it out from remaining_km, and cannot tell "next" from
             * "much later".
             */
            'schedule_status' => $this->when(
                $request->route('car') !== null,
                fn () => $this->km <= $request->route('car')->current_km ? 'passed' : 'upcoming',
            ),

            'car_model'    => new CarModelResource($this->whenLoaded('carModel')),

            // Gap F1/F3 — the checklist, resolved override-then-catalogue, in
            // the same response rather than behind a second request.
            'items'        => ServiceItemResource::collection($this->whenLoaded('checklist')),
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
