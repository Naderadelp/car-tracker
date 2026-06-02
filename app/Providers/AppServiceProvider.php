<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Brand;
use App\Models\CarLog;
use App\Models\CarModel;
use App\Models\Document;
use App\Models\FillUp;
use App\Models\FuelPrice;
use App\Models\Item;
use App\Models\ParkingRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCenter;
use App\Models\Trip;
use App\Models\User;
use App\Events\CarLogCreated;
use App\Events\GasStationCheckIn;
use App\Events\OdometerAdvanced;
use App\Listeners\CheckUpcomingServicesNotification;
use App\Listeners\SendGasStationReminderNotification;
use App\Listeners\SendServiceCompletedNotification;
use App\Observers\TripObserver;
use Illuminate\Support\Facades\Event;
use App\Policies\BrandPolicy;
use App\Policies\CarLogPolicy;
use App\Policies\CarModelPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\FillUpPolicy;
use App\Policies\FuelPricePolicy;
use App\Policies\ItemPolicy;
use App\Policies\ParkingRecordPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\ServiceCenterPolicy;
use App\Policies\ServicePolicy;
use App\Policies\TripPolicy;
use App\Policies\UserPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Trip::observe(TripObserver::class);
        Event::listen(CarLogCreated::class, SendServiceCompletedNotification::class);
        Event::listen(GasStationCheckIn::class, SendGasStationReminderNotification::class);
        Event::listen(OdometerAdvanced::class, CheckUpcomingServicesNotification::class);

        Gate::before(function (User $user, string $ability) {
            return $user->isAdmin() ? true : null;
        });

        Gate::policy(Brand::class,      BrandPolicy::class);
        Gate::policy(CarModel::class,   CarModelPolicy::class);
        Gate::policy(Document::class,   DocumentPolicy::class);
        Gate::policy(FillUp::class,        FillUpPolicy::class);
        Gate::policy(FuelPrice::class,     FuelPricePolicy::class);
        Gate::policy(Item::class,          ItemPolicy::class);
        Gate::policy(ParkingRecord::class, ParkingRecordPolicy::class);
        Gate::policy(Service::class,       ServicePolicy::class);
        Gate::policy(ServiceCenter::class, ServiceCenterPolicy::class);
        Gate::policy(Trip::class,          TripPolicy::class);
        Gate::policy(Role::class,       RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(User::class,       UserPolicy::class);
        Gate::policy(CarLog::class,     CarLogPolicy::class);
    }
}
