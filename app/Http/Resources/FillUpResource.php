<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FillUpResource extends JsonResource
{
    /**
     * Gap A2 — km/L per record.
     *
     * The figure depends on the *preceding* fill-up, so it cannot be derived
     * from one model in isolation and must not be stored (a later insertion or
     * an odometer correction would leave a stored value wrong). The controller
     * computes the whole series once and hands it to the resource here, which
     * keeps it to one pass over the data rather than a query per row.
     *
     * @var array<int, float|null>
     */
    private static array $efficiency = [];

    /**
     * @param array<int, float|null> $series
     */
    public static function withEfficiency(array $series): void
    {
        self::$efficiency = $series;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'car_id'     => $this->car_id,
            'liters'     => $this->liters,
            'tank_percentage' => $this->tank_percentage,
            'odometer'   => $this->odometer,
            'cost_egp'   => $this->cost_egp,
            'fill_date'  => $this->fill_date?->toDateString(),
            // null where undefined — the first fill-up has no distance before
            // it, and a downward odometer correction leaves no meaningful
            // figure between two records.
            'km_per_liter' => self::$efficiency[$this->id] ?? null,
            'fuel_type'   => $this->fuel_type,
            'station_name' => $this->station_name,
            'station_lat' => $this->station_lat,
            'station_lng' => $this->station_lng,
            'created_at' => $this->created_at,
        ];
    }
}
