<?php

namespace Src\Domain\Vehicle\Repositories\Eloquent;

use Src\Domain\Vehicle\Entities\Vehicle;
use Src\Domain\Vehicle\Repositories\Contracts\VehicleRepository;
use Src\Infrastructure\AbstractRepositories\EloquentRepository;

class VehicleRepositoryEloquent extends EloquentRepository implements VehicleRepository
{
    public function model(): string
    {
        return Vehicle::class;
    }
}
