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

    protected function scopeToUser(): void
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $this->model = $this->model->where('user_id', auth()->id());
        }
    }
}
