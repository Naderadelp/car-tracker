<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Document;
use App\Models\FillUp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Observers\TripObserver;
use App\Policies\BrandPolicy;
use App\Policies\CarModelPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\FillUpPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\TripPolicy;
use App\Policies\UserPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Trip::observe(TripObserver::class);

        Gate::before(function (User $user, string $ability) {
            return $user->isAdmin() ? true : null;
        });

        Gate::policy(Brand::class,      BrandPolicy::class);
        Gate::policy(CarModel::class,   CarModelPolicy::class);
        Gate::policy(Document::class,   DocumentPolicy::class);
        Gate::policy(FillUp::class,     FillUpPolicy::class);
        Gate::policy(Trip::class,       TripPolicy::class);
        Gate::policy(Role::class,       RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(User::class,       UserPolicy::class);
    }
}
