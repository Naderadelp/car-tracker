<?php

namespace App\Repositories\Eloquent;

use App\Models\Vehicle;
use App\Repositories\Contracts\VehicleRepository;

class VehicleRepositoryEloquent extends EloquentRepository implements VehicleRepository
{
    public function model(): string
    {
        return Vehicle::class;
    }
}
