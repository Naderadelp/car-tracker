<?php

namespace Src\Domain\Vehicle\Entities\Traits;

trait VehicleAttributes
{
    protected function casts(): array
    {
        return [
            'year'                  => 'integer',
            'current_mileage'       => 'integer',
            'has_warranty'          => 'boolean',
            'warranty_limit_km'     => 'integer',
            'warranty_expiry_date'  => 'date',
        ];
    }
}
