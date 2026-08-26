<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One checklist line — gap F1 and F3.
 *
 * `name` and `price` resolve override-then-catalogue, so the client gets a
 * single flat shape whether the line came from the admin-managed catalogue or
 * the driver typed it in themselves.
 */
class ServiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'item_id'   => $this->item_id,
            'name'      => $this->resolvedName(),
            'price'     => $this->resolvedPrice(),
            'is_custom' => $this->isCustom(),
        ];
    }
}
