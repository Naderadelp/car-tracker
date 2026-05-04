<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\DocumentRepository;
use App\Repositories\Contracts\ParkingRecordRepository;
use App\Repositories\Contracts\PermissionRepository;
use App\Repositories\Contracts\RoleRepository;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Contracts\CarRepository;
use App\Repositories\Contracts\FillUpRepository;
use App\Repositories\Contracts\TripRepository;
use App\Repositories\Eloquent\CarRepositoryEloquent;
use App\Repositories\Eloquent\DocumentRepositoryEloquent;
use App\Repositories\Eloquent\FillUpRepositoryEloquent;
use App\Repositories\Eloquent\ParkingRecordRepositoryEloquent;
use App\Repositories\Eloquent\PermissionRepositoryEloquent;
use App\Repositories\Eloquent\RoleRepositoryEloquent;
use App\Repositories\Eloquent\TripRepositoryEloquent;
use App\Repositories\Eloquent\UserRepositoryEloquent;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, UserRepositoryEloquent::class);
        $this->app->bind(CarRepository::class, CarRepositoryEloquent::class);
        $this->app->bind(FillUpRepository::class, FillUpRepositoryEloquent::class);
        $this->app->bind(TripRepository::class, TripRepositoryEloquent::class);
        $this->app->bind(ParkingRecordRepository::class, ParkingRecordRepositoryEloquent::class);
        $this->app->bind(DocumentRepository::class, DocumentRepositoryEloquent::class);
        $this->app->bind(RoleRepository::class, RoleRepositoryEloquent::class);
        $this->app->bind(PermissionRepository::class, PermissionRepositoryEloquent::class);
    }
}
