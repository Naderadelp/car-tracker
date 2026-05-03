<?php

namespace App\Repositories\Eloquent;

use App\Models\Car;
use App\Repositories\Contracts\CarRepository;

class CarRepositoryEloquent extends EloquentRepository implements CarRepository
{
    protected array $allowedIncludes = ['brand', 'carModel'];

    public function model(): string
    {
        return Car::class;
    }

    protected function scopeToUser(): void
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $this->model = $this->model->where('user_id', auth()->id());
        }
    }
}
