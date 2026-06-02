<?php

namespace App\Events;

use App\Models\Car;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GasStationCheckIn
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Car $car
    ) {}
}
