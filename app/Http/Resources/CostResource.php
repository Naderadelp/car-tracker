<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'car_id'     => $this->car_id,
            'spent_at'   => $this->spent_at?->toDateString(),
            'title'      => $this->title,
            'amount_egp' => $this->amount_egp,
            'category'   => $this->category,

            /*
             * What makes decision D2 legible to the client. `source` null means
             * the driver typed this in; otherwise it names the fuel record or
             * maintenance entry it came from, so the app can mark it as derived
             * and let the driver spot a manual duplicate.
             */
            'source' => $this->source_type === null ? null : [
                'type' => $this->source_type,
                'id'   => $this->source_id,
            ],

            /*
             * True once the driver has corrected a carried-across amount. From
             * then on the totals use their figure and the source record no
             * longer overwrites it.
             */
            'amount_overridden' => $this->amount_overridden,

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
