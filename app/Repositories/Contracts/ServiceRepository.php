<?php

namespace App\Repositories\Contracts;

use App\Models\Car;
use Illuminate\Support\Collection;

interface ServiceRepository extends RepositoryInterface
{
    public function upcomingForCar(Car $car): Collection;
}
