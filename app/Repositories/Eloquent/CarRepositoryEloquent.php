<?php

namespace App\Repositories\Eloquent;

use App\Models\Car;
use App\Repositories\Contracts\CarRepository;

class CarRepositoryEloquent extends EloquentRepository implements CarRepository
{
    protected array $allowedIncludes      = ['brand', 'carModel', 'fillUps', 'trips', 'parkingRecords'];
    protected array $allowedFiltersExact  = ['brand_id', 'car_model_id', 'user_id'];
    protected array $allowedSorts         = ['current_km', 'created_at'];
    protected array $allowedDefaultSorts  = ['-id'];

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
