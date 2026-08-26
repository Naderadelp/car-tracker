<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'car_id'       => $this->car_id,
            'type'         => $this->type,
            'expiry_date'  => $this->expiry_date?->toDateString(),

            /*
             * FR-007. The app drives a red row and a "needs attention" alert
             * off this. It is derived from the date on every read, never
             * stored, so correcting a past date to a future one moves the
             * record from `expired` back to `valid` with no extra write.
             */
            'status'       => $this->resolveStatus(),
            'has_file'     => $this->media_count !== null
                ? $this->media_count > 0
                : $this->getMedia('vehicle_documents')->isNotEmpty(),
            'created_at'   => $this->created_at->toISOString(),
            'updated_at'   => $this->updated_at->toISOString(),
            'user'         => new UserResource($this->whenLoaded('user')),
            'media'        => MediaResource::collection($this->whenLoaded('media')),
        ];
    }

    /**
     * `no_expiry` is deliberately distinct from `valid`: a registration
     * document that never expires is not the same thing as one that expires
     * next year, and the app sorts and colours them differently.
     */
    private function resolveStatus(): string
    {
        if ($this->expiry_date === null) {
            return 'no_expiry';
        }

        return $this->expiry_date->isPast() ? 'expired' : 'valid';
    }
}
