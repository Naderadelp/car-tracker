<?php

namespace Src\Domain\Vehicle\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Domain\Vehicle\Repositories\Contracts\VehicleRepository;
use Src\Domain\Vehicle\Repositories\Eloquent\VehicleRepositoryEloquent;

class VehicleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VehicleRepository::class, VehicleRepositoryEloquent::class);
    }
}
