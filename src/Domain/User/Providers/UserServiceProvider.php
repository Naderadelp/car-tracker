<?php

namespace Src\Domain\User\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Domain\User\Repositories\Contracts\UserRepository;
use Src\Domain\User\Repositories\Eloquent\UserRepositoryEloquent;

class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, UserRepositoryEloquent::class);
    }
}
