<?php

namespace App\Observers;

use App\Events\OdometerAdvanced;
use App\Models\Trip;

class TripObserver
{
    public function created(Trip $trip): void
    {
        $car = $trip->car;
        $car->current_km += (int) round($trip->total_distance_km);
        $car->save();

        event(new OdometerAdvanced($car, $car->current_km));
    }
}
