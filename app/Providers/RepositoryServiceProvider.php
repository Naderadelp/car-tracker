<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Contracts\VehicleRepository;
use App\Repositories\Eloquent\UserRepositoryEloquent;
use App\Repositories\Eloquent\VehicleRepositoryEloquent;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, UserRepositoryEloquent::class);
        $this->app->bind(VehicleRepository::class, VehicleRepositoryEloquent::class);
    }
}
