<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\CarLogRepository;
use App\Repositories\Contracts\CarRepository;
use App\Repositories\Contracts\DeviceTokenRepository;
use App\Repositories\Contracts\DocumentRepository;
use App\Repositories\Contracts\FillUpRepository;
use App\Repositories\Contracts\FuelPriceRepository;
use App\Repositories\Contracts\IssueRepository;
use App\Repositories\Contracts\ItemRepository;
use App\Repositories\Contracts\ParkingRecordRepository;
use App\Repositories\Contracts\PermissionRepository;
use App\Repositories\Contracts\CostRepository;
use App\Repositories\Contracts\ReminderRepository;
use App\Repositories\Contracts\RoleRepository;
use App\Repositories\Contracts\ServiceCenterRepository;
use App\Repositories\Contracts\ServiceRepository;
use App\Repositories\Contracts\TripRepository;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Eloquent\CarLogRepositoryEloquent;
use App\Repositories\Eloquent\CarRepositoryEloquent;
use App\Repositories\Eloquent\DeviceTokenRepositoryEloquent;
use App\Repositories\Eloquent\DocumentRepositoryEloquent;
use App\Repositories\Eloquent\FillUpRepositoryEloquent;
use App\Repositories\Eloquent\FuelPriceRepositoryEloquent;
use App\Repositories\Eloquent\IssueRepositoryEloquent;
use App\Repositories\Eloquent\ItemRepositoryEloquent;
use App\Repositories\Eloquent\ParkingRecordRepositoryEloquent;
use App\Repositories\Eloquent\PermissionRepositoryEloquent;
use App\Repositories\Eloquent\CostRepositoryEloquent;
use App\Repositories\Eloquent\ReminderRepositoryEloquent;
use App\Repositories\Eloquent\RoleRepositoryEloquent;
use App\Repositories\Eloquent\ServiceCenterRepositoryEloquent;
use App\Repositories\Eloquent\ServiceRepositoryEloquent;
use App\Repositories\Eloquent\TripRepositoryEloquent;
use App\Repositories\Eloquent\UserRepositoryEloquent;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, UserRepositoryEloquent::class);
        $this->app->bind(CarRepository::class, CarRepositoryEloquent::class);
        $this->app->bind(CarLogRepository::class, CarLogRepositoryEloquent::class);
        $this->app->bind(CostRepository::class, CostRepositoryEloquent::class);
        $this->app->bind(IssueRepository::class, IssueRepositoryEloquent::class);
        $this->app->bind(ReminderRepository::class, ReminderRepositoryEloquent::class);
        $this->app->bind(FillUpRepository::class, FillUpRepositoryEloquent::class);
        $this->app->bind(TripRepository::class, TripRepositoryEloquent::class);
        $this->app->bind(ParkingRecordRepository::class, ParkingRecordRepositoryEloquent::class);
        $this->app->bind(ServiceCenterRepository::class, ServiceCenterRepositoryEloquent::class);
        $this->app->bind(ServiceRepository::class, ServiceRepositoryEloquent::class);
        $this->app->bind(ItemRepository::class, ItemRepositoryEloquent::class);
        $this->app->bind(DocumentRepository::class, DocumentRepositoryEloquent::class);
        $this->app->bind(RoleRepository::class, RoleRepositoryEloquent::class);
        $this->app->bind(PermissionRepository::class, PermissionRepositoryEloquent::class);
        $this->app->bind(DeviceTokenRepository::class, DeviceTokenRepositoryEloquent::class);
        $this->app->bind(FuelPriceRepository::class, FuelPriceRepositoryEloquent::class);
    }
}
