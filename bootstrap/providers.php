<?php

use App\Providers\AppServiceProvider;
use Src\Domain\Auth\Providers\AuthServiceProvider;
use Src\Domain\User\Providers\UserServiceProvider;
use Src\Domain\Vehicle\Providers\VehicleServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    UserServiceProvider::class,
    VehicleServiceProvider::class,
];
